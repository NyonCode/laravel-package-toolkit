<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use Illuminate\Support\Facades\File;
use NyonCode\LaravelPackageToolkit\Commands\InstallCommand;
use NyonCode\LaravelPackageToolkit\Packager;

trait PackageInstallPublishMigrationsTest
{
    public function configure(Packager $packager): void
    {
        $packager->name('Test Package')
            ->hasMigrations()
            ->hasInstallCommand(function (InstallCommand $command) {
                $command->publishMigrations();
            });
    }
}

uses(PackageInstallPublishMigrationsTest::class);

test('install command with migrations publishes migration files', function () {
    $this->artisan('test-package:install', ['--no-interaction' => true, '--force' => true])
        ->assertExitCode(0);

    $published = false;
    foreach (File::allFiles(__DIR__.'/../TestPackageData/database/migrations') as $file) {
        if (file_exists(database_path('migrations/'.$file->getFilename()))) {
            $published = true;
            @unlink(database_path('migrations/'.$file->getFilename()));
        }
    }

    expect($published)->toBeTrue();
});

test('install command shows publishing step for migrations', function () {
    $this->artisan('test-package:install', ['--force' => true])
        ->expectsOutputToContain('Publishing migrations');
});
