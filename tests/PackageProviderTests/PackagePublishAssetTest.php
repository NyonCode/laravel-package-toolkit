<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use Illuminate\Support\Facades\File;
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

        expect(public_path('vendor/test-package/css/index.css'))->toBeFile()
            ->and(public_path('vendor/test-package/js/index.js'))->toBeFile();

        File::deleteDirectory(public_path('vendor/test-package'));
    }
);
