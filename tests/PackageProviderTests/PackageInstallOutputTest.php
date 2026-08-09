<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use Illuminate\Support\Facades\File;
use NyonCode\LaravelPackageToolkit\Commands\InstallCommand;
use NyonCode\LaravelPackageToolkit\Packager;

trait PackageInstallOutputTest
{
    public function configure(Packager $packager): void
    {
        $packager->name('Test Package')
            ->hasConfig('test-config.php')
            ->hasMigrations()
            ->hasSeeders()
            ->hasInstallCommand(
                fn (InstallCommand $command) => $command
                    ->publishConfig()
                    ->publishMigrations()
                    ->publishSeeders()
            );
    }
}

uses(PackageInstallOutputTest::class);

afterEach(function () {
    File::delete(config_path('test-config.php'));
    File::delete(config_path('test-package.php'));
});

test('the steps are numbered in the order they run', function () {
    $this->artisan('test-package:install')
        ->expectsOutputToContain('(1/3) Publishing configuration')
        ->expectsOutputToContain('(2/3) Publishing migrations')
        ->expectsOutputToContain('(3/3) Publishing seeders')
        ->assertSuccessful();
});

test('the next steps point at what was published', function () {
    $this->artisan('test-package:install')
        ->expectsOutputToContain('Run: php artisan migrate')
        ->expectsOutputToContain('Run: php artisan db:seed')
        ->assertSuccessful();
});

/**
 * The configuration step is looked up as `config/{short-name}.php`, so it only appears
 * for a package whose config file is named after the package itself.
 */
test('the configuration step appears once a config named after the package exists', function () {
    File::put(config_path('test-package.php'), '<?php return [];');

    $this->artisan('test-package:install')
        ->expectsOutputToContain('Review configuration in config/test-package.php')
        ->assertSuccessful();
});

test('the configuration step is absent when no such config was published', function () {
    $this->artisan('test-package:install')
        ->doesntExpectOutputToContain('Review configuration')
        ->assertSuccessful();
});

test('nothing is printed when the command runs unattended', function () {
    $this->artisan('test-package:install', ['--no-interaction' => true])
        ->doesntExpectOutputToContain('installed successfully')
        ->doesntExpectOutputToContain('Published config')
        ->assertSuccessful();
});
