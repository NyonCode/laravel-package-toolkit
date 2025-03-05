<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use NyonCode\LaravelPackageToolkit\Packager;

trait PackagePublishAssetTest
{
    public function configure(Packager $packager): void
    {
        $packager->name('Test Package')
            ->hasAssets();
    }
}

uses(PackagePublishAssetTest::class);

test(
    description: 'can publish assets',
    closure: function () {
        $this->artisan('vendor:publish --tag=test-package::assets')->assertExitCode(0);
    }
);