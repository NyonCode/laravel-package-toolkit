<?php

namespace NyonCode\LaravelPackageToolkit\Tests\Unit;

use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->packager = (new Packager())->name('Test Package');
});

test('a package has no view composers by default', function () {
    expect($this->packager->isViewComposable())->toBeFalse()
        ->and($this->packager->viewComposers())->toBe([]);
});

test('a single view gets a composer', function () {
    $composer = fn () => null;

    $this->packager->hasViewComposer('test-page', $composer);

    expect($this->packager->isViewComposable())->toBeTrue()
        ->and($this->packager->viewComposers())->toBe(['test-page' => $composer]);
});

test('a list of views shares one composer', function () {
    $composer = fn () => null;

    $this->packager->hasViewComposer(['first', 'second'], $composer);

    expect($this->packager->viewComposers())->toBe([
        'first' => $composer,
        'second' => $composer,
    ]);
});

test('composers accumulate across calls', function () {
    $this->packager
        ->hasViewComposer('first', fn () => null)
        ->hasViewComposer('second', fn () => null);

    expect($this->packager->viewComposers())->toHaveKeys(['first', 'second']);
});
