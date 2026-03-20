<?php

/**
 * Class PackageTimelessMigrationOnRunTest
 *
 * @project   laravel-package-toolkit
 *
 * @author    Ondřej Nyklíček
 *
 * @created   20.03.2026
 */

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use Artisan;
use Illuminate\Support\Facades\Schema;
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\Tests\TestPackageData\src\TestServiceProvider;

trait PackageTimelessMigrationOnRunTest
{
    /**
     * @throws \Exception
     */
    public function configure(Packager $package): void
    {
        $package
            ->name('Package test')
            ->hasMigrations(directory: '../database/timeless_migrations')
            ->canLoadMigrations();
    }
}

uses(PackageTimelessMigrationOnRunTest::class);

test('timeless migrations can be loaded and run', function () {
    Artisan::call('migrate');

    expect(Schema::hasTable('posts'))->toBeTrue()
        ->and(Schema::hasTable('comments'))->toBeTrue();
});

test('timeless migrations are detected as having no date prefix', function () {
    $provider = app()->getProvider(TestServiceProvider::class);
    $reflection = new \ReflectionProperty($provider, 'packager');
    $reflection->setAccessible(true);

    /** @var Packager $packager */
    $packager = $reflection->getValue($provider);

    expect($packager->shouldPrependTimestamp())->toBeTrue();
});
