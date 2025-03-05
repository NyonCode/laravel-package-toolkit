<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\Tests\TestPackageData\commands\TestCommand;
use Symfony\Component\Console\Exception\CommandNotFoundException;

trait PackageCommandTest
{

    public function configure(Packager $packager): void
    {
        $packager->name('Test Package')
            ->hasCommand(TestCommand::class);
    }
}

uses(PackageCommandTest::class);

test(
    description: 'can call command',
    closure: function () {
        $this->artisan('app:test')->assertSuccessful();
    }
);

test("can't call second command", function () {
    $this->expectException(CommandNotFoundException::class);
    $this->artisan('app:second-test');
});