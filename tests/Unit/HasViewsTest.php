<?php

namespace NyonCode\LaravelPackageToolkit\Tests\Unit;

use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\Tests\TestCase;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;

uses(TestCase::class);

beforeEach(function () {
    $this->packager = (new Packager())->name('Test Package');
    $this->packager->hasBasePath(__DIR__.'/../TestPackageData/src');
});

test('a package has no views until they are declared', function () {
    expect($this->packager->isViewable())->toBeFalse()
        ->and($this->packager->views())->toBe('');
});

test('the default directory is resolved relative to the package', function () {
    $this->packager->hasViews();

    expect($this->packager->isViewable())->toBeTrue()
        ->and($this->packager->views())
        ->toBe($this->packager->path('../resources/views'));
});

test('a custom relative directory is resolved against the base path', function () {
    $this->packager->hasViews('../resources/views');

    expect($this->packager->views())->toBe($this->packager->path('../resources/views'));
});

test('an absolute path is taken as given', function () {
    $absolute = realpath(__DIR__.'/../TestPackageData/resources/views');

    $this->packager->hasViews($absolute);

    expect($this->packager->views())->toBe($absolute)
        ->and($this->packager->isViewable())->toBeTrue();
});

test('a views directory that does not exist is rejected', function () {
    expect(fn () => $this->packager->hasViews('../nope'))
        ->toThrow(DirectoryNotFoundException::class, 'Directory [../nope] does not exist');
});

test('an absolute path that does not exist is rejected', function () {
    $missing = DIRECTORY_SEPARATOR.implode(DIRECTORY_SEPARATOR, ['tmp', 'lpt-missing-views']);

    expect(fn () => $this->packager->hasViews($missing))
        ->toThrow(DirectoryNotFoundException::class);
});
