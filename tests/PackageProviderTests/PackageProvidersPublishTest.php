<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use NyonCode\LaravelPackageToolkit\Packager;

trait PackageProvidersPublishTest
{
    public function configure(Packager $packager): void
    {
        $packager->name('Test package')
            ->hasProviders([
                '../stubs/MyProvider.stub',
                '../stubs/SecondProvider.stub',
            ]);
    }
}

uses(PackageProvidersPublishTest::class);

test(
    description: 'can publish the package providers',
    closure: function () {
        $this->artisan('vendor:publish --tag=test-package::providers')->assertExitCode(0);
    }
);