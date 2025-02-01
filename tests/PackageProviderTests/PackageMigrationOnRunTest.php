<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use NyonCode\LaravelPackageToolkit\Exceptions\PackagerException;
use NyonCode\LaravelPackageToolkit\Packager;

trait PackageMigrationOnRunTest
{
    /**
     * @throws PackagerException
     */
    public function configure(Packager $package): void
    {
        $package->name('Package test')->hasMigrations()->canLoadMigrations();
    }
}

uses(PackageMigrationOnRunTest::class);

test('Package test', function () {
    Artisan::call('migrate');

    expect(Schema::hasTable('laravel-package_table'))->toBeTrue();
});