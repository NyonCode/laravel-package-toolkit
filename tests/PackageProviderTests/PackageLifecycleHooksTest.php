<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\Support\Enums\LifecycleHook;

trait PackageLifecycleHooksTest
{
    private bool $bootingCalled = false;
    private bool $bootedCalled = false;
    private bool $registeringCalled = false;
    private bool $registeredCalled = false;

    public function configure(Packager $package): void
    {
        $package->name('Package lifecycle hooks test')
            ->bootingPackage(function () {
                $this->bootingCalled = true;
            })
            ->bootedPackage(function () {
                $this->bootedCalled = true;
            })
            ->registeringPackage(function () {
                $this->registeringCalled = true;
            })
            ->registeredPackage(function () {
                $this->registeredCalled = true;
            });
    }
}

test('booting package hook is executed when bootingPackage is called', function () {
    // Reset flag
    $this->bootingCalled = false;

    // Call the service provider method that should execute the hook
    $this->bootingPackage();

    // Assert the hook was called
    $this->assertTrue($this->bootingCalled);
});

test('booted package hook is executed when bootedPackage is called', function () {
    // Reset flag
    $this->bootedCalled = false;

    // Call the service provider method that should execute the hook
    $this->bootedPackage();

    // Assert the hook was called
    $this->assertTrue($this->bootedCalled);
});

test('registering package hook is executed when registeringPackage is called', function () {
    // Reset flag
    $this->registeringCalled = false;

    // Call the service provider method that should execute the hook
    $this->registeringPackage();

    // Assert the hook was called
    $this->assertTrue($this->registeringCalled);
});

test('registered package hook is executed when registeredPackage is called', function () {
    // Reset flag
    $this->registeredCalled = false;

    // Call the service provider method that should execute the hook
    $this->registeredPackage();

    // Assert the hook was called
    $this->assertTrue($this->registeredCalled);
});

test('executeLifecycleHook executes booting hook directly', function () {
    // Reset flag
    $this->bootingCalled = false;

    // Execute hook directly on packager
    $this->packager->executeLifecycleHook(LifecycleHook::Booting);

    // Assert the hook was called
    $this->assertTrue($this->bootingCalled);
});

test('executeLifecycleHook executes booted hook directly', function () {
    // Reset flag
    $this->bootedCalled = false;

    // Execute hook directly on packager
    $this->packager->executeLifecycleHook(LifecycleHook::Booted);

    // Assert the hook was called
    $this->assertTrue($this->bootedCalled);
});

test('executeLifecycleHook executes registering hook directly', function () {
    // Reset flag
    $this->registeringCalled = false;

    // Execute hook directly on packager
    $this->packager->executeLifecycleHook(LifecycleHook::Registering);

    // Assert the hook was called
    $this->assertTrue($this->registeringCalled);
});

test('executeLifecycleHook executes registered hook directly', function () {
    // Reset flag
    $this->registeredCalled = false;

    // Execute hook directly on packager
    $this->packager->executeLifecycleHook(LifecycleHook::Registered);

    // Assert the hook was called
    $this->assertTrue($this->registeredCalled);
});

test('lifecycle hook execution with undefined hook does nothing', function () {
    $package = new Packager();
    $package->name('Test Package');

    // Execute hook without defining it - should not throw exception
    $package->executeLifecycleHook(LifecycleHook::Booting);

    // If we get here, no exception was thrown
    $this->assertTrue(true);
});

test('lifecycle hooks maintain proper defined flags', function () {
    $package = new Packager();
    $package->name('Test Package');

    // Initially, no hooks should be defined
    $this->assertFalse($package->bootingDefined);
    $this->assertFalse($package->bootedDefined);
    $this->assertFalse($package->registeringDefined);
    $this->assertFalse($package->registeredDefined);

    // After defining hooks, flags should be true
    $package->bootingPackage(fn() => null);
    $package->bootedPackage(fn() => null);
    $package->registeringPackage(fn() => null);
    $package->registeredPackage(fn() => null);

    $this->assertTrue($package->bootingDefined);
    $this->assertTrue($package->bootedDefined);
    $this->assertTrue($package->registeringDefined);
    $this->assertTrue($package->registeredDefined);
});

test('lifecycle hook callbacks receive package instance', function () {
    $package = new Packager();
    $package->name('Test Package');
    $receivedInstance = null;

    $package->bootingPackage(function ($instance) use (&$receivedInstance) {
        $receivedInstance = $instance;
    });

    $package->executeLifecycleHook(LifecycleHook::Booting);

    $this->assertSame($package, $receivedInstance);
});

test('multiple hooks can be chained fluently', function () {
    $package = new Packager();

    $result = $package->name('Test Package')
        ->bootingPackage(fn() => null)
        ->bootedPackage(fn() => null)
        ->registeringPackage(fn() => null)
        ->registeredPackage(fn() => null);

    $this->assertSame($package, $result);
    $this->assertTrue($package->bootingDefined);
    $this->assertTrue($package->bootedDefined);
    $this->assertTrue($package->registeringDefined);
    $this->assertTrue($package->registeredDefined);
});