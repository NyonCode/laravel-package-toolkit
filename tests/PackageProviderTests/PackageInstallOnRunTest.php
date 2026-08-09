<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use NyonCode\LaravelPackageToolkit\Commands\InstallCommand;
use NyonCode\LaravelPackageToolkit\Packager;

/**
 * `installOnRun()` runs the install command itself, unattended, once the application has
 * booted — so the assertions below are about what already happened during setUp.
 */
trait PackageInstallOnRunTest
{
    public bool $installed = false;

    public array $interaction = [];

    public function configure(Packager $packager): void
    {
        $packager->name('Test Package')
            ->hasInstallCommand(function (InstallCommand $command) {
                $command->beforeInstallation(function (InstallCommand $command) {
                    $this->installed = true;
                    $this->interaction[] = $command->option('no-interaction');
                });
            })
            ->installOnRun();
    }
}

uses(PackageInstallOnRunTest::class);

test('the package installs itself once the application has booted', function () {
    expect($this->installed)->toBeTrue();
});

test('the automatic installation runs unattended', function () {
    expect($this->interaction)->toBe([true]);
});

test('the install command is still registered for a manual run', function () {
    $this->artisan('test-package:install', ['--no-interaction' => true])
        ->assertSuccessful();
});
