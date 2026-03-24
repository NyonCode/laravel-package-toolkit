<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use Illuminate\Support\Facades\File;
use NyonCode\LaravelPackageToolkit\Packager;

trait PackageInstallPresetsTest
{
    public function configure(Packager $packager): void
    {
        $packager->name('Test Package')
            ->hasConfig()
            ->hasMigrations()
            ->hasAssets()
            ->hasQuickInstall();
    }
}

uses(PackageInstallPresetsTest::class);

test('hasQuickInstall registers install command', function () {
    $this->artisan('test-package:install', ['--no-interaction' => true])
        ->assertSuccessful();
});

test('hasQuickInstall publishes config migrations and assets', function () {
    $this->artisan('test-package:install', ['--no-interaction' => true, '--force' => true])
        ->assertExitCode(0)
        ->expectsOutputToContain('Publishing configuration')
        ->expectsOutputToContain('Publishing migrations')
        ->expectsOutputToContain('Publishing assets');

    // Cleanup published config files
    foreach (File::files(__DIR__.'/../TestPackageData/config') as $file) {
        @unlink(config_path($file->getFilename()));
    }

    // Cleanup published migration files
    foreach (File::allFiles(__DIR__.'/../TestPackageData/database/migrations') as $file) {
        @unlink(database_path('migrations/'.$file->getFilename()));
    }
});
