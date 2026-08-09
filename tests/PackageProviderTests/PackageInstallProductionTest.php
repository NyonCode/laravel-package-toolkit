<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use NyonCode\LaravelPackageToolkit\Commands\InstallCommand;
use NyonCode\LaravelPackageToolkit\Packager;

/**
 * Installing in production asks first, and `silent()` keeps the hook chatter out of the
 * output either way.
 */
trait PackageInstallProductionTest
{
    public function configure(Packager $packager): void
    {
        $packager->name('Test Package')
            ->hasInstallCommand(
                fn (InstallCommand $command) => $command
                    ->silent()
                    ->beforeInstallation(function () {
                        $this->hookRan = true;
                    })
            );
    }
}

uses(PackageInstallProductionTest::class);

beforeEach(function () {
    $this->hookRan = false;
    $this->app['env'] = 'production';
});

test('a declined production install stops before the hooks run', function () {
    $this->artisan('test-package:install')
        ->expectsConfirmation('⚠️  You are in production environment. Are you sure you want to continue?', 'no')
        ->expectsOutputToContain('Installation cancelled.')
        ->assertSuccessful();

    expect($this->hookRan)->toBeFalse();
});

test('a confirmed production install proceeds', function () {
    $this->artisan('test-package:install')
        ->expectsConfirmation('⚠️  You are in production environment. Are you sure you want to continue?', 'yes')
        ->expectsOutputToContain('installed successfully')
        ->assertSuccessful();

    expect($this->hookRan)->toBeTrue();
});

test('--force skips the production question', function () {
    $this->artisan('test-package:install', ['--force' => true])
        ->expectsOutputToContain('installed successfully')
        ->assertSuccessful();

    expect($this->hookRan)->toBeTrue();
});

test('a silent command does not announce its hooks', function () {
    $this->artisan('test-package:install', ['--force' => true])
        ->doesntExpectOutputToContain('Before Installation hooks')
        ->assertSuccessful();
});
