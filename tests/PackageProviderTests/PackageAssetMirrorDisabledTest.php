<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\Support\PublishedAssets;

trait PackageAssetMirrorDisabledTest
{
    public function configure(Packager $packager): void
    {
        $packager->name('Test Package')
            ->hasAssets(mirror: false);
    }
}

uses(PackageAssetMirrorDisabledTest::class);

test('opting out leaves the mirror unregistered', function () {
    expect($this->app->bound(PublishedAssets::class))->toBeFalse();
});

test('opting out keeps the publish tags', function () {
    $this->artisan('vendor:publish --tag=test-package::assets')->assertExitCode(0);

    expect(public_path('vendor/test-package/css/index.css'))->toBeFile();
});
