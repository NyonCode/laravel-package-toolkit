<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use Illuminate\Support\Facades\Event;
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\Tests\TestPackageData\src\Worker\ListenerDependency;
use NyonCode\LaravelPackageToolkit\Tests\TestPackageData\src\Worker\Pinged;
use NyonCode\LaravelPackageToolkit\Tests\TestPackageData\src\Worker\PingListener;

trait PackageOctaneDiagnoseEventsSandboxTest
{
    protected function configurationBeforeProviders(): array
    {
        return ['package-toolkit.octane.sandbox_events' => true];
    }

    public function configure(Packager $packager): void
    {
        $packager->name('Test Package')
            ->registeredPackage(fn () => app()->singleton(ListenerDependency::class))
            ->bootedPackage(fn () => Event::listen(Pinged::class, PingListener::class));
    }
}

uses(PackageOctaneDiagnoseEventsSandboxTest::class);

test('a listener is not reported once the dispatcher follows the operation', function () {
    $this->artisan('package-toolkit:octane', ['--strict' => true])
        ->doesntExpectOutputToContain('PingListener')
        ->assertSuccessful();
});
