<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\Support\Worker\WorkerLifecycle;
use NyonCode\LaravelPackageToolkit\Tests\SecondPackage\SecondService;
use NyonCode\LaravelPackageToolkit\Tests\SecondPackage\SecondServiceProvider;
use NyonCode\LaravelPackageToolkit\Tests\SecondPackage\SecondState;
use NyonCode\LaravelPackageToolkit\Tests\TestPackageData\src\TestServiceProvider;
use NyonCode\LaravelPackageToolkit\Tests\TestPackageData\src\Worker\LazyService;
use NyonCode\LaravelPackageToolkit\Tests\TestPackageData\src\Worker\Stateful;

trait PackageWorkerTwoPackagesTest
{
    protected function getPackageProviders($app): array
    {
        return [TestServiceProvider::class, SecondServiceProvider::class];
    }

    protected function configurationBeforeProviders(): array
    {
        return [
            'package-toolkit.octane.enabled' => true,
            'package-toolkit.octane.sandbox_providers' => false,
            'package-toolkit.octane.isolate' => ['second-package'],
        ];
    }

    public function configure(Packager $packager): void
    {
        Stateful::$log = [];
        SecondState::$log = [];

        $packager->name('Test Package')
            ->registeredPackage(fn () => app()->singleton(LazyService::class));
    }
}

uses(PackageWorkerTwoPackagesTest::class);

test('both packages share one lifecycle', function () {
    expect(app(WorkerLifecycle::class)->isolations())->toHaveKeys(['second-package'])
        ->not->toHaveKey('test-package');
});

test('isolating one package leaves the other one alone', function () {
    $first = app()->getProvider(TestServiceProvider::class);
    $second = app()->getProvider(SecondServiceProvider::class);

    [$lazy, $service] = octaneOperation(function () use ($first, $second) {
        Stateful::$log[] = 'kept';
        SecondState::$log[] = 'reset';

        return [
            providerApplication($first)->make(LazyService::class),
            providerApplication($second)->make(SecondService::class),
        ];
    });

    // `resolved()` stays true after `forgetInstance()`, so compare the instances.
    expect(app()->make(LazyService::class))->toBe($lazy)
        ->and(app()->make(SecondService::class))->not->toBe($service)
        ->and(Stateful::$log)->toBe(['kept'])
        ->and(SecondState::$log)->toBe([]);
});
