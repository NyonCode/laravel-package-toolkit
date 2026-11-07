<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\Support\Worker\WorkerLifecycle;
use NyonCode\LaravelPackageToolkit\Tests\TestPackageData\src\TestServiceProvider;

trait PackageWorkerDisabledTest
{
    public function configure(Packager $packager): void
    {
        $packager->name('Test Package');
    }
}

uses(PackageWorkerDisabledTest::class);

test('nothing is installed outside a long-lived worker', function () {
    expect(app()->bound(WorkerLifecycle::class))->toBeFalse();
});

test('octane events leave the provider alone outside a long-lived worker', function () {
    $provider = app()->getProvider(TestServiceProvider::class);

    $during = octaneOperation(fn () => providerApplication($provider));

    expect($during)->toBe(app());
});
