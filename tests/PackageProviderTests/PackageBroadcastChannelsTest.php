<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use Illuminate\Support\Facades\Broadcast;
use NyonCode\LaravelPackageToolkit\Packager;

trait PackageBroadcastChannelsTest
{
    public function configure(Packager $packager): void
    {
        $packager->name('Test package')
            ->hasBroadcastChannels(directory: '../broadcasting');
    }
}

uses(PackageBroadcastChannelsTest::class);

test('can register broadcast channels from every channel file', function () {
    $channels = Broadcast::getChannels()->keys()->all();

    expect($channels)->toContain('test-package.room.{roomId}')
        ->and($channels)->toContain('test-package.presence');
});

test('channel files are not registered as routes', function () {
    expect(collect(app('router')->getRoutes()->getRoutes())
        ->contains(fn ($route) => str_contains($route->uri(), 'test-package.room')))
        ->toBeFalse();
});
