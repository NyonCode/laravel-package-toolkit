<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use Exception;
use NyonCode\LaravelPackageToolkit\Packager;

trait PackageAlternativeCommandsDynamicTest
{
    /**
     * @throws Exception
     */
    public function configure(Packager $package): void
    {
        $package
            ->name('Package command test')
            ->hasCommands(directory: '../alternativeCommands');
    }
}

uses(PackageAlternativeCommandsDynamicTest::class);

test(
    description: 'can call six command',
    closure: function () {
        $this->artisan('app:six-test')->assertExitCode(0);
    }
);
