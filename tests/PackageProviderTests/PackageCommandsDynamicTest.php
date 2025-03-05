<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\Tests\TestPackageData\commands\TestCommand;
use Symfony\Component\Console\Exception\CommandNotFoundException;

trait PackageCommandsDynamicTest
{
    public function configure(Packager $package): void
    {
        $package
            ->name('Package command test')
            ->hasCommands(directory: 'commands');
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