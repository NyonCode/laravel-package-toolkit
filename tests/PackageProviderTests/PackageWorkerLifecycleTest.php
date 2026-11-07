<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use Illuminate\Support\Facades\Event;
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\Support\PublishedAssets;
use NyonCode\LaravelPackageToolkit\Support\Worker\WorkerLifecycle;
use NyonCode\LaravelPackageToolkit\Tests\TestPackageData\src\TestServiceProvider;
use NyonCode\LaravelPackageToolkit\Tests\TestPackageData\src\Worker\BootRegistry;
use NyonCode\LaravelPackageToolkit\Tests\TestPackageData\src\Worker\LazyService;
use NyonCode\LaravelPackageToolkit\Tests\TestPackageData\src\Worker\ListenerDependency;
use NyonCode\LaravelPackageToolkit\Tests\TestPackageData\src\Worker\Pinged;
use NyonCode\LaravelPackageToolkit\Tests\TestPackageData\src\Worker\PingListener;

trait PackageWorkerLifecycleTest
{
    protected function configurationBeforeProviders(): array
    {
        return ['package-toolkit.octane.enabled' => true];
    }

    public function configure(Packager $packager): void
    {
        $packager->name('Test Package')
            ->hasAssets('assets')
            ->registeredPackage(function (): void {
                app()->singleton(LazyService::class);
                app()->singleton(BootRegistry::class);
                app()->singleton(ListenerDependency::class);
            })
            ->bootedPackage(function (): void {
                app(BootRegistry::class)->entries[] = 'from-boot';
                Event::listen(Pinged::class, PingListener::class);
            });
    }
}

uses(PackageWorkerLifecycleTest::class);

test('the lifecycle is installed once for the application', function () {
    expect(app()->bound(WorkerLifecycle::class))->toBeTrue()
        ->and(app(WorkerLifecycle::class)->sandboxesProviders())->toBeTrue()
        ->and(app(WorkerLifecycle::class)->sandboxesEvents())->toBeFalse();
});

test('a provider holds the operation clone while it runs and the worker afterwards', function (string $event) {
    $base = app();
    $provider = $base->getProvider(TestServiceProvider::class);

    $during = octaneOperation(fn ($sandbox) => [providerApplication($provider), $sandbox], $event);

    expect($during[0])->toBe($during[1])
        ->and($during[0])->not->toBe($base)
        ->and(providerApplication($provider))->toBe($base);
})->with(['request', 'task', 'tick']);

test('a singleton resolved through the provider stays with the operation', function () {
    $base = app();
    $provider = $base->getProvider(TestServiceProvider::class);

    $first = octaneOperation(fn () => providerApplication($provider)->make(LazyService::class));
    $second = octaneOperation(fn () => providerApplication($provider)->make(LazyService::class));

    expect($first)->not->toBe($second)
        ->and($base->resolved(LazyService::class))->toBeFalse();
});

test('a registry the package filled at boot is the same in every operation', function () {
    $first = octaneOperation(fn () => app(BootRegistry::class));
    $second = octaneOperation(fn () => app(BootRegistry::class));

    expect($first)->toBe($second)
        ->and($second->entries)->toBe(['from-boot']);
});

test('the event dispatcher keeps the worker container unless asked otherwise', function () {
    octaneOperation(fn () => event(new Pinged()));

    expect(app()->resolved(ListenerDependency::class))->toBeTrue();
});

test('the asset mirror forgets its resolved urls after every operation', function () {
    $shipped = __DIR__.'/../TestPackageData/assets/js/index.js';
    $published = public_path('vendor/test-package/js/index.js');

    $before = octaneOperation(fn () => app(PublishedAssets::class)->url('test-package', $shipped));

    touch($published, filemtime($published) + 10);
    clearstatcache(true, $published);

    $after = octaneOperation(fn () => app(PublishedAssets::class)->url('test-package', $shipped));

    expect($after)->not->toBe($before)
        ->and($after)->toEndWith('?id='.filemtime($published));
});
