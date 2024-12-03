<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use NyonCode\LaravelPackageToolkit\Packager;

trait PackageMigrationTest
{
    public function configure(Packager $package): void
    {
        $package->name('Package test')->hasMigrations();
    }
}

uses(PackageMigrationTest::class);

test('Package test', function () {
    $this->artisan('vendor:publish --tag=package-test::migrations')
        ->assertExitCode(0);
});