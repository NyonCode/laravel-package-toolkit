<?php

namespace NyonCode\LaravelPackageToolkit\Tests\Unit;

use Illuminate\Support\Facades\File;
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->packager = (new Packager())->name('Test Package');
    $this->packager->hasBasePath(__DIR__.'/../TestPackageData/src');

    $this->composerPath = __DIR__.'/../TestPackageData/composer.json';
});

afterEach(function () {
    File::delete($this->composerPath);
});

test('a package is not aboutable until it asks to be', function () {
    expect($this->packager->isAboutable())->toBeFalse()
        ->and($this->packager->aboutData())->toBe([]);
});

test('hasAbout can be turned on and back off', function () {
    expect($this->packager->hasAbout()->isAboutable())->toBeTrue()
        ->and($this->packager->hasAbout(false)->isAboutable())->toBeFalse();
});

test('a declared version wins over composer.json', function () {
    File::put($this->composerPath, json_encode(['name' => 'acme/blog']));

    $this->packager->hasVersion('9.9.9');

    expect($this->packager->getVersion())->toBe('9.9.9');
});

test('the version is null without a composer.json', function () {
    expect($this->packager->getVersion())->toBeNull();
});

test('a composer.json for a package that is not installed reports no version', function () {
    File::put($this->composerPath, json_encode(['name' => 'acme/blog']));

    expect($this->packager->getVersion())->toBeNull();
});

test('the version comes from the installed package named in composer.json', function () {
    File::put($this->composerPath, json_encode(['name' => 'nyoncode/laravel-package-toolkit']));

    expect($this->packager->getVersion())->toBeString()->not->toBeEmpty();
});

test('about data is stored as declared', function () {
    $this->packager->setAboutData(['Docs' => 'https://example.test']);

    expect($this->packager->aboutData())->toBe(['Docs' => 'https://example.test']);
});
