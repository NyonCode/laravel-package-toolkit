<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

/**
 * Class PackageMixedMigrationTest
 *
 * @project   laravel-package-toolkit
 *
 * @author    Ondřej Nyklíček
 *
 * @created   20.03.2026
 */

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\Tests\TestPackageData\src\TestServiceProvider;
use ReflectionProperty;

trait PackageMixedMigrationTest
{
    public function configure(Packager $package): void
    {
        $package
            ->name('Package test')
            ->hasMigrations(directory: '../database/mixed_migrations')
            ->canLoadMigrations();
    }
}

uses(PackageMixedMigrationTest::class);

test('mixed migrations can be loaded and run', function () {
    Artisan::call('migrate');

    expect(Schema::hasTable('categories'))->toBeTrue()
        ->and(Schema::hasTable('tags'))->toBeTrue();
});

test('mixed migrations publish correctly', function () {
    $this->artisan('vendor:publish', ['--tag' => 'package-test::migrations'])
        ->assertExitCode(0);

    $publishedFiles = collect(File::files(database_path('migrations')))
        ->map(fn ($file) => $file->getFilename())
        ->filter(fn ($name) => str_contains($name, 'create_categories_table')
            || str_contains($name, 'create_tags_table'))
        ->values();

    expect($publishedFiles)->toHaveCount(2);

    // All published files should have timestamp prefix
    $publishedFiles->each(function ($filename) {
        expect($filename)->toMatch('/^\d{4}_\d{2}_\d{2}_\d{6}_/');
    });

    // The timestamped migration should keep its original timestamp
    $categoriesFile = $publishedFiles->first(fn ($name) => str_contains($name, 'create_categories_table'));
    expect($categoriesFile)->toStartWith('2025_06_15_100000_');

    // Clean up
    $publishedFiles->each(function ($filename) {
        $path = database_path('migrations/'.$filename);
        if (file_exists($path)) {
            unlink($path);
        }
    });
});

test('mapping preserves original timestamp for dated migrations', function () {
    $provider = app()->getProvider(TestServiceProvider::class);
    $reflection = new ReflectionProperty($provider, 'packager');
    $reflection->setAccessible(true);

    /** @var Packager $packager */
    $packager = $reflection->getValue($provider);

    $mapping = $packager->getMigrationPublishMapping();
    $destinations = array_values($mapping);

    $datedFile = collect($destinations)->first(fn ($path) => str_contains($path, 'create_categories_table'));
    $timelessFile = collect($destinations)->first(fn ($path) => str_contains($path, 'create_tags_table'));

    // Dated migration keeps its original prefix
    expect(basename($datedFile))->toStartWith('2025_06_15_100000_');

    // Timeless migration gets a new prefix
    expect(basename($timelessFile))->toMatch('/^\d{4}_\d{2}_\d{2}_\d{6}_create_tags_table\.php$/');
});
