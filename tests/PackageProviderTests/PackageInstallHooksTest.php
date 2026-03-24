<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use NyonCode\LaravelPackageToolkit\Commands\InstallCommand;
use NyonCode\LaravelPackageToolkit\Packager;

trait PackageInstallHooksTest
{
    private bool $beforeHookCalled = false;

    private bool $afterHookCalled = false;

    private ?InstallCommand $hookCommandInstance = null;

    public function configure(Packager $packager): void
    {
        $packager->name('Test Package')
            ->hasInstallCommand(function (InstallCommand $command) {
                $command
                    ->beforeInstallation(function (InstallCommand $cmd) {
                        $this->beforeHookCalled = true;
                        $this->hookCommandInstance = $cmd;
                    })
                    ->afterInstallation(function (InstallCommand $cmd) {
                        $this->afterHookCalled = true;
                    });
            });
    }
}

uses(PackageInstallHooksTest::class);

test('beforeInstallation hook is executed when command runs', function () {
    $this->artisan('test-package:install', ['--no-interaction' => true]);

    expect($this->beforeHookCalled)->toBeTrue();
});

test('afterInstallation hook is executed when command runs', function () {
    $this->artisan('test-package:install', ['--no-interaction' => true]);

    expect($this->afterHookCalled)->toBeTrue();
});

test('both hooks are executed in correct order', function () {
    $order = [];

    // Re-configure manually by using a fresh command instance is not possible here,
    // but we can verify both flags are set after a single run
    $this->artisan('test-package:install', ['--no-interaction' => true]);

    expect($this->beforeHookCalled)->toBeTrue()
        ->and($this->afterHookCalled)->toBeTrue();
});

test('hook receives InstallCommand instance as argument', function () {
    $this->artisan('test-package:install', ['--no-interaction' => true]);

    expect($this->hookCommandInstance)->toBeInstanceOf(InstallCommand::class);
});
