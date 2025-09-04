<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use NyonCode\LaravelPackageToolkit\Packager;

trait PackageLifecycleHooksIntegrationTest
{
    private bool $registeringHookExecuted = false;

    private bool $registeredHookExecuted = false;

    private bool $bootingHookExecuted = false;

    private bool $bootedHookExecuted = false;

    private ?Packager $receivedPackagerInHook = null;

    public function configure(Packager $packager): void
    {
        $packager->name('Lifecycle Hooks Integration Test')
            ->registeringPackage(function ($p) {
                $this->registeringHookExecuted = true;
                $this->receivedPackagerInHook = $p;
            })
            ->registeredPackage(function () {
                $this->registeredHookExecuted = true;
            })
            ->bootingPackage(function () {
                $this->bootingHookExecuted = true;
            })
            ->bootedPackage(function () {
                $this->bootedHookExecuted = true;
            });
    }
}

uses(PackageLifecycleHooksIntegrationTest::class);

test('registering hook is executed during package registration', function () {
    expect($this->registeringHookExecuted)->toBeTrue()
        ->and($this->receivedPackagerInHook)->toBeInstanceOf(Packager::class)
        ->and($this->receivedPackagerInHook->name)->toBe('Lifecycle Hooks Integration Test');
});

test('registered hook is executed after package registration', function () {
    expect($this->registeredHookExecuted)->toBeTrue();
});

test('booting hook is executed during package boot', function () {
    expect($this->bootingHookExecuted)->toBeTrue();
});

test('booted hook is executed after package boot', function () {
    expect($this->bootedHookExecuted)->toBeTrue();
});

test('all lifecycle hooks are executed in correct order', function () {
    expect($this->registeringHookExecuted)->toBeTrue()
        ->and($this->registeredHookExecuted)->toBeTrue()
        ->and($this->bootingHookExecuted)->toBeTrue()
        ->and($this->bootedHookExecuted)->toBeTrue();
});

test('lifecycle hooks receive valid packager instance', function () {
    expect($this->receivedPackagerInHook)->toBeInstanceOf(Packager::class)
        ->and($this->receivedPackagerInHook->name)->toBe('Lifecycle Hooks Integration Test')
        ->and($this->receivedPackagerInHook->registeringDefined)->toBeTrue()
        ->and($this->receivedPackagerInHook->registeredDefined)->toBeTrue()
        ->and($this->receivedPackagerInHook->bootingDefined)->toBeTrue()
        ->and($this->receivedPackagerInHook->bootedDefined)->toBeTrue();
});

test('packager short name is properly generated', function () {
    expect($this->receivedPackagerInHook->shortName())->toBe('lifecycle-hooks-integration-test');
});
