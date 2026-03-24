<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use NyonCode\LaravelPackageToolkit\Commands\InstallCommand;
use NyonCode\LaravelPackageToolkit\Packager;

trait PackageInstallCommandTest
{
    public function configure(Packager $packager): void
    {
        $packager->name('Test Package')
            ->hasInstallCommand();
    }
}

uses(PackageInstallCommandTest::class);

test('install command is registered and runs successfully', function () {
    $this->artisan('test-package:install', ['--no-interaction' => true])
        ->assertSuccessful();
});

test('install command exits with code 0', function () {
    $this->artisan('test-package:install', ['--no-interaction' => true])
        ->assertExitCode(0);
});

test('install command has correct signature', function () {
    $this->artisan('test-package:install --help')
        ->assertSuccessful();
});

test('install command supports --force option', function () {
    $this->artisan('test-package:install', ['--force' => true, '--no-interaction' => true])
        ->assertSuccessful();
});

test('install command warns when no installation steps configured', function () {
    $this->artisan('test-package:install', ['--no-interaction' => true])
        ->expectsOutputToContain('No installation steps configured');
});
