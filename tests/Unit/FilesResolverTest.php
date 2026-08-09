<?php

namespace NyonCode\LaravelPackageToolkit\Tests\Unit;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\Tests\TestCase;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;

uses(TestCase::class);

beforeEach(function () {
    $this->packager = (new Packager())->name('Test Package');
    $this->packager->hasBasePath(__DIR__.'/../TestPackageData/src');
});

// Base path
test('the base path is empty until one is set', function () {
    expect((new Packager())->basePath())->toBe('');
});

test('hasBasePath returns the path it stored', function () {
    $packager = new Packager();

    expect($packager->hasBasePath(__DIR__))->toBe(__DIR__)
        ->and($packager->basePath())->toBe(__DIR__);
});

test('a provider living in src/Providers resolves to the package src directory', function () {
    $packager = new Packager();

    $stored = $packager->hasBasePath(
        implode(DIRECTORY_SEPARATOR, ['', 'pkg', 'src', 'Providers'])
    );

    expect($stored)->toBe(implode(DIRECTORY_SEPARATOR, ['', 'pkg', 'src']));
});

test('duplicate and trailing separators are normalized away', function () {
    $packager = new Packager();
    $separator = DIRECTORY_SEPARATOR;

    expect($packager->hasBasePath("{$separator}pkg{$separator}{$separator}src{$separator}"))
        ->toBe("{$separator}pkg{$separator}src");
});

test('path joins the relative path onto the base path', function () {
    expect($this->packager->path('Commands'))
        ->toBe($this->packager->basePath().DIRECTORY_SEPARATOR.'Commands');
});

// resolveFiles — explicit files
test('resolves a single file name inside a directory', function () {
    $files = $this->packager->resolveFiles('test.php', '../routes');

    expect($files)->toHaveCount(1)
        ->and($files[0]->getBaseFileName())->toBe('test');
});

test('resolves a list of file names', function () {
    $files = $this->packager->resolveFiles(['test.php', 'foo.php'], '../routes');

    expect($files)->toHaveCount(2);
});

test('resolves a path that is already relative to the package', function () {
    $files = $this->packager->resolveFiles('../routes/test.php');

    expect($files)->toHaveCount(1)
        ->and($files[0]->getBaseFileName())->toBe('test');
});

test('a missing file is reported with the resource type', function () {
    expect(fn () => $this->packager->resolveFiles('missing.php', '../routes', 'route'))
        ->toThrow(FileNotFoundException::class, 'Route file [missing.php] does not exist in directory [../routes].');
});

test('a missing file without a type is still reported', function () {
    expect(fn () => $this->packager->resolveFiles('missing.php', '../routes'))
        ->toThrow(FileNotFoundException::class, 'File [missing.php] does not exist in directory [../routes].');
});

// resolveFiles — discovery
test('discovers every file in a directory when none are named', function () {
    $files = $this->packager->resolveFiles(null, '../routes');

    expect($files)->toHaveCount(2)
        ->and(collect($files)->map->getBaseFileName()->sort()->values()->all())
        ->toBe(['foo', 'test']);
});

test('an empty file list also discovers the directory', function () {
    expect($this->packager->resolveFiles([], '../routes'))->toHaveCount(2);
});

test('discovering an unknown directory throws', function () {
    expect(fn () => $this->packager->resolveFiles(null, '../does-not-exist'))
        ->toThrow(DirectoryNotFoundException::class);
});
