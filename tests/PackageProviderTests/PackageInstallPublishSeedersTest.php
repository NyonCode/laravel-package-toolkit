<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use Illuminate\Support\Facades\File;
use NyonCode\LaravelPackageToolkit\Commands\InstallCommand;
use NyonCode\LaravelPackageToolkit\Packager;

trait PackageInstallPublishSeedersTest
{
    public function configure(Packager $packager): void
    {
        $packager->name('Test Package')
            ->hasSeeders()
            ->hasFactories()
            ->hasStubs(['test-command.stub'])
            ->hasInstallCommand(function (InstallCommand $command) {
                $command->publishSeeders()
                    ->publishFactories()
                    ->publishStubs();
            });
    }
}

uses(PackageInstallPublishSeedersTest::class);

afterEach(function () {
    foreach (['seeders/TestSeeder.php', 'seeders/SecondTestSeeder.php', 'factories/TestModelFactory.php'] as $path) {
        if (file_exists(database_path($path))) {
            @unlink(database_path($path));
        }
    }

    File::deleteDirectory(base_path('stubs/test-package'));
});

test('install command publishes seeders, factories and stubs', function () {
    $this->artisan('test-package:install', ['--no-interaction' => true, '--force' => true])
        ->assertExitCode(0);

    expect(database_path('seeders/TestSeeder.php'))->toBeFile()
        ->and(database_path('factories/TestModelFactory.php'))->toBeFile()
        ->and(base_path('stubs/test-package/test-command.stub'))->toBeFile();
});

test('install command shows a publishing step for each new resource', function () {
    $this->artisan('test-package:install', ['--force' => true])
        ->expectsOutputToContain('Publishing seeders')
        ->expectsOutputToContain('Publishing factories')
        ->expectsOutputToContain('Publishing stubs');
});
