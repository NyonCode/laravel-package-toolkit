<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use NyonCode\LaravelPackageToolkit\Packager;

trait PackageProviderPublishTest
{
    public function configure(Packager $packager): void
    {
        $packager->name('Test package')
            ->hasProvider('../stubs/MyProvider.stub');
    }
}

uses(PackageProviderPublishTest::class);

test(
    description: 'can publish the package provider',
    closure: function () {
        $this->artisan('vendor:publish --tag=test-package::providers')->assertExitCode(0);
    }
);