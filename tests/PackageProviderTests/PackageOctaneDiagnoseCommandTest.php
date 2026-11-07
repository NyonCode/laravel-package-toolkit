<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use Illuminate\Support\Facades\Event;
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\Tests\TestPackageData\src\Worker\HoldsRequest;
use NyonCode\LaravelPackageToolkit\Tests\TestPackageData\src\Worker\ListenerDependency;
use NyonCode\LaravelPackageToolkit\Tests\TestPackageData\src\Worker\Pinged;
use NyonCode\LaravelPackageToolkit\Tests\TestPackageData\src\Worker\PingListener;

trait PackageOctaneDiagnoseCommandTest
{
    public function configure(Packager $packager): void
    {
        $packager->name('Test Package')
            ->registeredPackage(function (): void {
                app()->singleton(HoldsRequest::class);
                app()->singleton(ListenerDependency::class);
            })
            ->bootedPackage(function (): void {
                app(HoldsRequest::class);
                Event::listen(Pinged::class, PingListener::class);
            });
    }
}

uses(PackageOctaneDiagnoseCommandTest::class);

test('it reports an object held from boot with the request it took', function () {
    $this->artisan('package-toolkit:octane')
        ->expectsOutputToContain('takes Request $request')
        ->assertSuccessful();
});

test('it reports a class listener with a shared dependency', function () {
    $this->artisan('package-toolkit:octane')
        ->expectsOutputToContain('PingListener')
        ->assertSuccessful();
});

test('it lists static properties that hold arrays or objects', function () {
    $this->artisan('package-toolkit:octane')
        ->expectsOutputToContain('Stateful::$log')
        ->doesntExpectOutputToContain('Stateful::$mode')
        ->assertSuccessful();
});

test('it lists scalar static properties with --all', function () {
    $this->artisan('package-toolkit:octane', ['--all' => true])
        ->expectsOutputToContain('Stateful::$mode')
        ->assertSuccessful();
});

test('it fails with --strict when something needs attention', function () {
    $this->artisan('package-toolkit:octane', ['--strict' => true])->assertFailed();
});
