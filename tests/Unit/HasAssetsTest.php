<?php

namespace NyonCode\LaravelPackageToolkit\Tests\Unit;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use InvalidArgumentException;
use NyonCode\LaravelPackageToolkit\PackageConfigurationException;
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\Support\Asset;
use NyonCode\LaravelPackageToolkit\Tests\TestCase;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;

uses(TestCase::class);

beforeEach(function () {
    $this->packager = (new Packager())->name('Test Package');
    $this->packager->hasBasePath(__DIR__.'/../TestPackageData/src');
});

// Defaults
test('a package ships no assets until they are declared', function () {
    expect($this->packager->isAssetable())->toBeFalse()
        ->and($this->packager->assetDirectory())->toBe('')
        ->and($this->packager->assetEntries())->toBe([])
        ->and($this->packager->hasAssetEntries())->toBeFalse()
        ->and($this->packager->viteBase())->toBeNull()
        ->and($this->packager->mirrorsAssets())->toBeTrue();
});

// hasAssets
test('declaring an asset directory turns mirroring on by default', function () {
    $this->packager->hasAssets();

    expect($this->packager->isAssetable())->toBeTrue()
        ->and($this->packager->assetDirectory())->toBe($this->packager->path('../dist'))
        ->and($this->packager->mirrorsAssets())->toBeTrue();
});

test('mirroring can be turned off', function () {
    $this->packager->hasAssets(mirror: false);

    expect($this->packager->mirrorsAssets())->toBeFalse();
});

test('a missing asset directory is rejected', function () {
    expect(fn () => $this->packager->hasAssets('nope'))
        ->toThrow(DirectoryNotFoundException::class, 'does not exist');
});

// Discovery
test('naming no entries discovers them from the asset directory', function () {
    $this->packager->hasAssets();

    expect($this->packager->hasAssetEntries())->toBeTrue()
        ->and(collect($this->packager->assetEntries())->map->key()->all())
        ->toBe(['css/index.css', 'js/index.js', 'js/legacy.js']);
});

test('discovery follows the directory hasAssets was given, not dist', function () {
    $this->packager->hasAssets('assets');

    expect(collect($this->packager->assetEntries())->map->key()->all())
        ->toBe(['js/index.js']);
});

test('a discovered script is a module, since nothing on disk says otherwise', function () {
    $this->packager->hasAssets();

    $legacy = collect($this->packager->assetEntries())->firstWhere(fn (Asset $asset) => $asset->key() === 'js/legacy.js');

    expect($legacy->isModule())->toBeTrue();
});

test('naming entries replaces discovery rather than adding to it', function () {
    $this->packager->hasAssets(entries: ['css/index.css']);

    expect(collect($this->packager->assetEntries())->map->key()->all())
        ->toBe(['css/index.css']);
});

test('entries are kept in declaration order', function () {
    $this->packager->hasAssets(entries: ['css/index.css', 'js/index.js']);

    expect($this->packager->hasAssetEntries())->toBeTrue()
        ->and(collect($this->packager->assetEntries())->map->key()->all())
        ->toBe(['css/index.css', 'js/index.js']);
});

test('an entry declared as an Asset instance is kept as declared', function () {
    $this->packager->hasAssets(entries: [Asset::make('js/legacy.js')->classic()]);

    expect($this->packager->assetEntries()[0]->isModule())->toBeFalse();
});

test('a shipped file that does not exist is rejected', function () {
    expect(fn () => $this->packager->hasAssets(entries: ['css/missing.css']))
        ->toThrow(FileNotFoundException::class, 'Asset file [css/missing.css] does not exist');
});

test('a shipped file declared before hasAssets is rejected', function () {
    expect(fn () => $this->packager->hasViteAssets(['resources/css/index.css' => 'css/index.css']))
        ->toThrow(PackageConfigurationException::class, 'Call hasAssets() before declaring it.');
});

// hasViteAssets
test('a source-only entry needs no asset directory', function () {
    $this->packager->hasViteAssets(['resources/js/index.js']);

    expect($this->packager->isAssetable())->toBeFalse()
        ->and($this->packager->assetEntries()[0]->source())->toBe('resources/js/index.js')
        ->and($this->packager->assetEntries()[0]->file())->toBeNull();
});

test('the source => fallback shorthand declares both halves', function () {
    $this->packager->hasAssets();
    $this->packager->hasViteAssets(['resources/css/index.css' => 'css/index.css']);

    $asset = $this->packager->assetEntries()[0];

    expect($asset->source())->toBe('resources/css/index.css')
        ->and($asset->file())->toBe('css/index.css');
});

test('an Asset instance passes through hasViteAssets untouched', function () {
    $this->packager->hasAssets();
    $this->packager->hasViteAssets([Asset::vite('resources/js/index.js')->fallback('js/index.js')]);

    $entry = collect($this->packager->assetEntries())->firstWhere(fn (Asset $asset) => $asset->key() === 'js/index.js');

    expect($entry)->not->toBeNull()
        ->and($entry->source())->toBe('resources/js/index.js');
});

test('a vite source that does not exist is rejected', function () {
    expect(fn () => $this->packager->hasViteAssets(['resources/js/missing.js']))
        ->toThrow(FileNotFoundException::class, 'Vite source [resources/js/missing.js] does not exist');
});

test('a vite entry replaces the shipped entry for the same file, keeping its position', function () {
    $this->packager->hasAssets(entries: ['css/index.css', 'js/index.js']);
    $this->packager->hasViteAssets(['resources/js/index.js' => 'js/index.js']);

    $entries = $this->packager->assetEntries();

    expect($entries)->toHaveCount(2)
        ->and(collect($entries)->map->key()->all())->toBe(['css/index.css', 'js/index.js'])
        ->and($entries[1]->source())->toBe('resources/js/index.js');
});

// Presentation survives the replacement
test('a vite entry inherits the classic flag of the shipped file it replaces', function () {
    $this->packager->hasAssets(entries: [Asset::make('js/legacy.js')->classic()]);
    $this->packager->hasViteAssets(['resources/js/index.js' => 'js/legacy.js']);

    $entry = $this->packager->assetEntries()[0];

    expect($entry->isModule())->toBeFalse()
        ->and($entry->source())->toBe('resources/js/index.js')
        ->and($entry->tagAttributes())->toHaveKey('defer');
});

test('declared attributes survive the replacement, the newer one winning a collision', function () {
    $this->packager->hasAssets(entries: [
        Asset::make('js/legacy.js')->attributes(['data-legacy' => true, 'data-kind' => 'shipped']),
    ]);
    $this->packager->hasViteAssets([
        Asset::vite('resources/js/index.js')->fallback('js/legacy.js')->attributes(['data-kind' => 'vite']),
    ]);

    expect($this->packager->assetEntries()[0]->tagAttributes())
        ->toHaveKey('data-legacy', true)
        ->toHaveKey('data-kind', 'vite');
});

test('an explicit kind on the replacement stands, since only it could have said so', function () {
    $this->packager->hasAssets(entries: [Asset::make('css/index.css')]);
    $this->packager->hasViteAssets([
        Asset::vite('resources/css/index.css')->fallback('css/index.css')->asScript(),
    ]);

    expect($this->packager->assetEntries()[0]->isStylesheet())->toBeFalse();
});

test('an explicit kind is inherited when the replacement says nothing about it', function () {
    $this->packager->hasAssets(entries: [Asset::make('js/legacy.js')->asStylesheet()]);
    $this->packager->hasViteAssets(['resources/js/index.js' => 'js/legacy.js']);

    expect($this->packager->assetEntries()[0]->isStylesheet())->toBeTrue();
});

// viteBase
test('an explicit vite base is normalized', function () {
    $this->packager->hasViteAssets([], base: '\\vendor\\acme\\blog\\');

    expect($this->packager->viteBase())->toBe('vendor/acme/blog');
});

test('the vite base stays null when it is not declared', function () {
    $this->packager->hasViteAssets(['resources/js/index.js']);

    expect($this->packager->viteBase())->toBeNull();
});

// Asset-level validation reached through the packager
test('an asset must declare a file or a source', function () {
    expect(fn () => Asset::make(''))
        ->toThrow(InvalidArgumentException::class, 'Asset file cannot be empty')
        ->and(fn () => Asset::vite(''))
        ->toThrow(InvalidArgumentException::class, 'Asset source cannot be empty');
});
