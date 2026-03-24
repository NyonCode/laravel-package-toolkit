<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use Illuminate\Support\Facades\File;
use NyonCode\LaravelPackageToolkit\Commands\InstallCommand;
use NyonCode\LaravelPackageToolkit\Packager;

trait PackageInstallPublishConfigTest
{
    public function configure(Packager $packager): void
    {
        $packager->name('Test Package')
            ->hasConfig()
            ->hasInstallCommand(function (InstallCommand $command) {
                $command->publishConfig();
            });
    }
}

uses(PackageInstallPublishConfigTest::class);

test('install command with config publishes config files', function () {
    $this->artisan('test-package:install', ['--no-interaction' => true, '--force' => true])
        ->assertExitCode(0);

    foreach (File::files(__DIR__.'/../TestPackageData/config') as $file) {
        expect(config_path($file->getFilename()))->toBeFile();
        @unlink(config_path($file->getFilename()));
    }
});

test('install command shows publishing step for config', function () {
    $this->artisan('test-package:install', ['--force' => true])
        ->expectsOutputToContain('Publishing configuration');
});

test('install command with --force overwrites existing config', function () {
    // First installation
    $this->artisan('test-package:install', ['--no-interaction' => true, '--force' => true])
        ->assertExitCode(0);

    // Second installation with --force should also succeed
    $this->artisan('test-package:install', ['--no-interaction' => true, '--force' => true])
        ->assertExitCode(0);

    foreach (File::files(__DIR__.'/../TestPackageData/config') as $file) {
        @unlink(config_path($file->getFilename()));
    }
});
