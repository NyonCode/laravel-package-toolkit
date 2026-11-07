<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\Support\Worker\WorkerLifecycle;
use NyonCode\LaravelPackageToolkit\Tests\NestedPackage\NestedState;
use NyonCode\LaravelPackageToolkit\Tests\NestedPackage\Providers\NestedServiceProvider;
use NyonCode\LaravelPackageToolkit\Tests\TestPackageData\src\TestServiceProvider;

trait PackageWorkerNestedProviderTest
{
    protected function getPackageProviders($app): array
    {
        return [TestServiceProvider::class, NestedServiceProvider::class];
    }

    protected function configurationBeforeProviders(): array
    {
        return [
            'package-toolkit.octane.enabled' => true,
            'package-toolkit.octane.isolate' => ['nested-package'],
        ];
    }

    public function configure(Packager $packager): void
    {
        NestedState::$log = [];

        $packager->name('Test Package');
    }
}

uses(PackageWorkerNestedProviderTest::class);

test('a provider in a Providers namespace isolates the package one level up', function () {
    $namespaces = new \ReflectionProperty(
        $isolation = app(WorkerLifecycle::class)->isolations()['nested-package'],
        'namespaces',
    );

    expect($namespaces->getValue($isolation))->toBe(['NyonCode\\LaravelPackageToolkit\\Tests\\NestedPackage\\']);

    octaneOperation(function () {
        NestedState::$log[] = 'reset';
    });

    expect(NestedState::$log)->toBe([]);
});

test('the package namespace drops a trailing Providers segment only', function () {
    expect(WorkerLifecycle::packageNamespace(app()->getProvider(NestedServiceProvider::class)))
        ->toBe('NyonCode\\LaravelPackageToolkit\\Tests\\NestedPackage')
        ->and(WorkerLifecycle::packageNamespace(app()->getProvider(TestServiceProvider::class)))
        ->toBe('NyonCode\\LaravelPackageToolkit\\Tests\\TestPackageData\\src');
});
