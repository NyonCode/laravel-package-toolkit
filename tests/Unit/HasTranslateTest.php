<?php

namespace NyonCode\LaravelPackageToolkit\Tests\Unit;

use Illuminate\Support\Facades\File;
use NyonCode\LaravelPackageToolkit\Exceptions\InvalidLanguageDirectoryException;
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\Tests\TestCase;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;

uses(TestCase::class);

beforeEach(function () {
    $this->packager = (new Packager())->name('Test Package');
    $this->packager->hasBasePath(__DIR__.'/../TestPackageData/src');
});

test('a package is not translatable until translations are declared', function () {
    expect($this->packager->isTranslatable())->toBeFalse()
        ->and($this->packager->loadJsonTranslate())->toBeFalse()
        ->and($this->packager->translationPath())->toBe('');
});

test('the default lang directory is registered', function () {
    $this->packager->hasTranslations();

    expect($this->packager->isTranslatable())->toBeTrue()
        ->and($this->packager->translationPath())->toBe($this->packager->path('../lang'));
});

test('a json file anywhere under the directory switches json loading on', function () {
    $this->packager->hasTranslations('jsonLang');

    expect($this->packager->loadJsonTranslate())->toBeTrue()
        ->and($this->packager->isTranslatable())->toBeTrue();
});

test('php-only translations do not switch json loading on', function () {
    $this->packager->hasTranslations('regionLang');

    expect($this->packager->loadJsonTranslate())->toBeFalse();
});

test('region locales are validated on their language part only', function () {
    $this->packager->hasTranslations('regionLang');

    expect($this->packager->isTranslatable())->toBeTrue();
});

test('a directory that is not a language code is rejected', function () {
    expect(fn () => $this->packager->hasTranslations('invalidLang'))
        ->toThrow(InvalidLanguageDirectoryException::class, 'Invalid language directory');
});

test('the package stays untranslatable when the directory is rejected', function () {
    try {
        $this->packager->hasTranslations('invalidLang');
    } catch (InvalidLanguageDirectoryException) {
        // The declaration failed; nothing may have been registered.
    }

    expect($this->packager->isTranslatable())->toBeFalse()
        ->and($this->packager->translationPath())->toBe('');
});

test('a missing translation directory is rejected', function () {
    expect(fn () => $this->packager->hasTranslations('nope'))
        ->toThrow(DirectoryNotFoundException::class, 'does not exist');
});

test('an empty translation directory is a no-op', function () {
    $path = __DIR__.'/../TestPackageData/emptyLang';
    File::ensureDirectoryExists($path);

    try {
        $this->packager->hasTranslations('emptyLang');

        expect($this->packager->isTranslatable())->toBeFalse()
            ->and($this->packager->translationPath())->toBe('');
    } finally {
        File::deleteDirectory($path);
    }
});
