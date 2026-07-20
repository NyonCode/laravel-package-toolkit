<?php

namespace NyonCode\LaravelPackageToolkit\Tests\Unit;

use NyonCode\LaravelPackageToolkit\Packager;

beforeEach(function () {
    $this->packager = (new Packager())->name('Test Package');
});

test('is not optimizable by default', function () {
    expect($this->packager->isOptimizable())->toBeFalse()
        ->and($this->packager->optimizeCommands())->toBeEmpty();
});

test('registers an optimize and clear command', function () {
    $this->packager->hasOptimizeCommands(
        optimize: 'pkg:cache',
        clear: 'pkg:clear'
    );

    expect($this->packager->isOptimizable())->toBeTrue()
        ->and($this->packager->optimizeCommands())->toBe([
            ['optimize' => 'pkg:cache', 'clear' => 'pkg:clear', 'key' => null],
        ]);
});

test('accepts an optimize-only entry', function () {
    $this->packager->hasOptimizeCommands(optimize: 'pkg:cache');

    expect($this->packager->optimizeCommands())->toBe([
        ['optimize' => 'pkg:cache', 'clear' => null, 'key' => null],
    ]);
});

test('accepts a clear-only entry', function () {
    $this->packager->hasOptimizeCommands(clear: 'pkg:clear');

    expect($this->packager->optimizeCommands())->toBe([
        ['optimize' => null, 'clear' => 'pkg:clear', 'key' => null],
    ]);
});

test('stores a custom key', function () {
    $this->packager->hasOptimizeCommands(optimize: 'pkg:cache', key: 'custom');

    expect($this->packager->optimizeCommands()[0]['key'])->toBe('custom');
});

test('is a no-op when both optimize and clear are null', function () {
    $this->packager->hasOptimizeCommands();

    expect($this->packager->isOptimizable())->toBeFalse()
        ->and($this->packager->optimizeCommands())->toBeEmpty();
});

test('appends multiple entries', function () {
    $this->packager
        ->hasOptimizeCommands(optimize: 'pkg:cache-a', key: 'a')
        ->hasOptimizeCommands(clear: 'pkg:clear-b', key: 'b');

    expect($this->packager->optimizeCommands())->toHaveCount(2);
});
