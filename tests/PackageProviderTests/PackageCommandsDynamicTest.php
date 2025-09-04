<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use Exception;
use NyonCode\LaravelPackageToolkit\Packager;

trait PackageCommandsDynamicTest
{
    /**
     * @throws Exception
     */
    public function configure(Packager $package): void
    {
        $package
            ->name('Package command test')
            ->hasCommands();
    }
}

uses(PackageCommandsDynamicTest::class);

test(
    description: 'can call first command',
    closure: function () {
        $this->artisan('app:test')->assertExitCode(0);
    }
);

test(
    description: 'can call second command',
    closure: function () {
        $this->artisan('app:second-test')->assertExitCode(0);
    }
);

test(
    description: 'can call three command',
    closure: function () {
        $this->artisan('app:three-test')->assertExitCode(0);
    }
);

test(
    description: "can't call four command",
    closure: function () {
        $this->artisan('app:four-test')->assertExitCode(0);
    }
);

test(
    description: 'can call five command',
    closure: function () {
        $this->artisan('app:five-test')->assertExitCode(0);
    }
);
