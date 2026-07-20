<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use Illuminate\Support\ServiceProvider;
use NyonCode\LaravelPackageToolkit\Packager;

trait PackageOptimizeVariantsTest
{
    /**
     * Configure the packager instance
     */
    public function configure(Packager $packager): void
    {
        $packager
            ->name('Test Package')
            ->hasOptimizeCommands(optimize: 'test-package:cache-a', key: 'opt-a')
            ->hasOptimizeCommands(clear: 'test-package:clear-b', key: 'opt-b')
            ->hasOptimizeCommands(
                optimize: 'test-package:cache-c',
                clear: 'test-package:clear-c',
                key: 'opt-c'
            );
    }
}

uses(PackageOptimizeVariantsTest::class);

test('an optimize-only entry registers no clear command', function () {
    expect(ServiceProvider::$optimizeCommands)->toHaveKey('opt-a')
        ->and(ServiceProvider::$optimizeCommands['opt-a'])->toBe('test-package:cache-a')
        ->and(ServiceProvider::$optimizeClearCommands)->not->toHaveKey('opt-a');
});

test('a clear-only entry registers no optimize command', function () {
    expect(ServiceProvider::$optimizeClearCommands)->toHaveKey('opt-b')
        ->and(ServiceProvider::$optimizeClearCommands['opt-b'])->toBe('test-package:clear-b')
        ->and(ServiceProvider::$optimizeCommands)->not->toHaveKey('opt-b');
});

test('multiple entries register under their distinct keys', function () {
    expect(ServiceProvider::$optimizeCommands['opt-c'])->toBe('test-package:cache-c')
        ->and(ServiceProvider::$optimizeClearCommands['opt-c'])->toBe('test-package:clear-c');
});
