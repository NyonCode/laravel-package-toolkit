<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\Support\Worker\WorkerLifecycle;
use NyonCode\LaravelPackageToolkit\Tests\TestPackageData\src\Late\LateLoaded;
use NyonCode\LaravelPackageToolkit\Tests\TestPackageData\src\TestServiceProvider;
use NyonCode\LaravelPackageToolkit\Tests\TestPackageData\src\Worker\BootRegistry;
use NyonCode\LaravelPackageToolkit\Tests\TestPackageData\src\Worker\ForeignNamed;
use NyonCode\LaravelPackageToolkit\Tests\TestPackageData\src\Worker\LazyService;
use NyonCode\LaravelPackageToolkit\Tests\TestPackageData\src\Worker\Stateful;

trait PackageWorkerIsolationTest
{
    protected function configurationBeforeProviders(): array
    {
        return [
            'package-toolkit.octane.enabled' => true,
            // Leave the provider pointing at the worker, so the instances below really
            // do land on it — the leak isolation exists to clean up.
            'package-toolkit.octane.sandbox_providers' => false,
            'package-toolkit.octane.isolate' => ['test-package'],
        ];
    }

    public function configure(Packager $packager): void
    {
        Stateful::$log = [];
        Stateful::$mode = 'declared';

        $packager->name('Test Package')
            ->registeredPackage(function (): void {
                app()->singleton(LazyService::class);
                app()->singleton(BootRegistry::class);
                app()->singleton('acme.foreign', ForeignNamed::class);
            })
            ->bootedPackage(function (): void {
                app(BootRegistry::class)->entries[] = 'from-boot';
                Stateful::$mode = 'boot';
            });
    }
}

uses(PackageWorkerIsolationTest::class);

test('the package is isolated under its short name', function () {
    expect(app(WorkerLifecycle::class)->isolations())->toHaveKey('test-package');
});

test('an instance the package left on the worker is forgotten after the operation', function () {
    $provider = app()->getProvider(TestServiceProvider::class);

    $first = octaneOperation(fn () => providerApplication($provider)->make(LazyService::class));

    expect(app()->resolved(LazyService::class) && app()->make(LazyService::class) === $first)->toBeFalse();

    $second = octaneOperation(fn () => providerApplication($provider)->make(LazyService::class));

    expect($second)->not->toBe($first);
});

test('an instance bound under a name the package does not own is forgotten too', function () {
    $provider = app()->getProvider(TestServiceProvider::class);

    $first = octaneOperation(fn () => providerApplication($provider)->make('acme.foreign'));
    $second = octaneOperation(fn () => providerApplication($provider)->make('acme.foreign'));

    expect($second)->not->toBe($first);
});

test('what the package resolved at boot is kept', function () {
    $first = octaneOperation(fn () => app(BootRegistry::class));
    $second = octaneOperation(fn () => app(BootRegistry::class));

    expect($second)->toBe($first)
        ->and($second->entries)->toBe(['from-boot']);
});

test('static state is put back to what the booted worker had', function () {
    octaneOperation(function () {
        Stateful::$log[] = 'request';
        Stateful::$mode = 'dirty';
    });

    expect(Stateful::$log)->toBe([])
        ->and(Stateful::$mode)->toBe('boot');
});

test('a class first loaded after boot goes back to its declared default', function () {
    if (class_exists(LateLoaded::class, false)) {
        $this->markTestSkipped('LateLoaded was autoloaded by an earlier test in this process.');
    }

    octaneOperation(fn () => null);
    octaneOperation(fn () => LateLoaded::$hits = 5);

    expect(LateLoaded::$hits)->toBe(0);
});
