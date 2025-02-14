<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use Exception;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use NyonCode\LaravelPackageToolkit\Packager;

trait PackageMigrationOnRunTest
{
    /**
     * @throws Exception
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