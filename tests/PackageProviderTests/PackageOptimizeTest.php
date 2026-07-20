<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use Illuminate\Support\ServiceProvider;
use NyonCode\LaravelPackageToolkit\Packager;

trait PackageOptimizeTest
{
    /**
     * Configure the packager instance
     */
    public function configure(Packager $packager): void
    {
        $packager
            ->name('Test Package')
            ->hasOptimizeCommands(
                optimize: 'test-package:cache',
                clear: 'test-package:clear'
            );
    }
}

uses(PackageOptimizeTest::class);

test('registers the optimize command under the package short name', function () {
    expect(ServiceProvider::$optimizeCommands)
        ->toHaveKey('test-package')
        ->and(ServiceProvider::$optimizeCommands['test-package'])
        ->toBe('test-package:cache');
});

test('registers the optimize:clear command under the package short name', function () {
    expect(ServiceProvider::$optimizeClearCommands)
        ->toHaveKey('test-package')
        ->and(ServiceProvider::$optimizeClearCommands['test-package'])
        ->toBe('test-package:clear');
});
