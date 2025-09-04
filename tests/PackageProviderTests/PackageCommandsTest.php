<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\Tests\TestPackageData\src\Commands\FiveTestCommand;
use NyonCode\LaravelPackageToolkit\Tests\TestPackageData\src\Commands\SecondTestCommand;
use NyonCode\LaravelPackageToolkit\Tests\TestPackageData\src\Commands\ThreeTestCommand;
use Symfony\Component\Console\Exception\CommandNotFoundException;

trait PackageCommandsTest
{
    public function configure(Packager $package): void
    {
        $package
            ->name('Package command test')
            ->hasCommand(\NyonCode\LaravelPackageToolkit\Tests\TestPackageData\src\Commands\TestCommand::class)
            ->hasCommands(SecondTestCommand::class)
            ->hasCommands([ThreeTestCommand::class, FiveTestCommand::class]);
    }
}

uses(PackageCommandsTest::class);

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
        $this->expectException(CommandNotFoundException::class);
        $this->artisan('app:four-test');
    }
);

test(
    description: 'can call five command',
    closure: function () {
        $this->artisan('app:five-test')->assertExitCode(0);
    }
);
