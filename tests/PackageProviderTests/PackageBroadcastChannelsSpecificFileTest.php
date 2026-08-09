<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use Illuminate\Support\Facades\Broadcast;
use NyonCode\LaravelPackageToolkit\Packager;

trait PackageBroadcastChannelsSpecificFileTest
{
    public function configure(Packager $packager): void
    {
        $packager->name('Test package')
            ->hasBroadcastChannels(['channels.php'], '../broadcasting');
    }
}

uses(PackageBroadcastChannelsSpecificFileTest::class);

test('can register a single named channel file', function () {
    $channels = Broadcast::getChannels()->keys()->all();

    expect($channels)->toContain('test-package.room.{roomId}')
        ->and($channels)->not->toContain('test-package.presence');
});

test('channels are not published', function () {
    $this->artisan('vendor:publish --tag=test-package::channels')
        ->assertExitCode(0);

    expect(base_path('routes/vendor/test-package/channels.php'))->not->toBeFile();
});
