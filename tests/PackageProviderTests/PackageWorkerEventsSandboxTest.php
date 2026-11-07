<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Event;
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\Tests\TestPackageData\src\Worker\ListenerDependency;
use NyonCode\LaravelPackageToolkit\Tests\TestPackageData\src\Worker\Pinged;
use NyonCode\LaravelPackageToolkit\Tests\TestPackageData\src\Worker\PingListener;
use NyonCode\LaravelPackageToolkit\Tests\TestPackageData\src\Worker\SharedPingListener;

trait PackageWorkerEventsSandboxTest
{
    protected function configurationBeforeProviders(): array
    {
        return [
            'package-toolkit.octane.enabled' => true,
            'package-toolkit.octane.sandbox_events' => true,
        ];
    }

    public function configure(Packager $packager): void
    {
        $packager->name('Test Package')
            ->registeredPackage(function (): void {
                app()->singleton(ListenerDependency::class);
                app()->singleton(SharedPingListener::class);
            })
            ->bootedPackage(function (): void {
                Event::listen(Pinged::class, PingListener::class);
                Event::listen(Pinged::class, SharedPingListener::class);
            });
    }
}

uses(PackageWorkerEventsSandboxTest::class);

test('a class listener is built on the operation clone', function () {
    $first = octaneOperation(function ($sandbox) {
        event(new Pinged());

        return $sandbox->resolved(ListenerDependency::class) ? $sandbox->make(ListenerDependency::class) : null;
    });

    $second = octaneOperation(function ($sandbox) {
        event(new Pinged());

        return $sandbox->make(ListenerDependency::class);
    });

    expect($first)->toBeInstanceOf(ListenerDependency::class)
        ->and($second)->not->toBe($first)
        ->and(app()->resolved(ListenerDependency::class))->toBeFalse();
});

test('the dispatcher is handed back to the worker after the operation', function () {
    octaneOperation(fn () => null);

    $container = (new \ReflectionProperty(Dispatcher::class, 'container'))->getValue(app('events'));

    expect($container)->toBe(app());
});

test('a listener bound as a singleton stays one instance for the worker', function () {
    $first = octaneOperation(function ($sandbox) {
        event(new Pinged());

        return $sandbox->make(SharedPingListener::class);
    });

    $second = octaneOperation(function ($sandbox) {
        event(new Pinged());

        return $sandbox->make(SharedPingListener::class);
    });

    expect($second)->toBe($first)
        ->and(app()->make(SharedPingListener::class))->toBe($first);
});
