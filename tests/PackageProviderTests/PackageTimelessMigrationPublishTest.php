<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

/**
 * Class PackageTimelessMigrationPublishTest
 *
 * @project   laravel-package-toolkit
 *
 * @author    Ondřej Nyklíček
 *
 * @created   20.03.2026
 */

use Illuminate\Support\Facades\File;
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\Tests\TestPackageData\src\TestServiceProvider;

trait PackageTimelessMigrationPublishTest
{
    public function configure(Packager $package): void
    {
        $package
            ->name('Package test')
            ->hasMigrations(directory: '../database/timeless_migrations');
    }
}

uses(PackageTimelessMigrationPublishTest::class);

test('timeless migrations are published with timestamp prefix', function () {
    $this->artisan('vendor:publish', ['--tag' => 'package-test::migrations'])
        ->assertExitCode(0);

    $publishedFiles = collect(File::files(database_path('migrations')))
        ->map(fn ($file) => $file->getFilename())
        ->filter(fn ($name) => str_contains($name, 'create_posts_table') || str_contains($name, 'create_comments_table'))
        ->values();

    expect($publishedFiles)->toHaveCount(2);

    $publishedFiles->each(function ($filename) {
        // Each published file should have a YYYY_MM_DD_HHMMSS_ prefix
        expect($filename)->toMatch('/^\d{4}_\d{2}_\d{2}_\d{6}_/');
    });

    // Clean up
    $publishedFiles->each(function ($filename) {
        $path = database_path('migrations/'.$filename);
        if (file_exists($path)) {
            unlink($path);
        }
    });
});

test('timeless migration publish mapping generates sequential timestamps', function () {
    $provider = app()->getProvider(TestServiceProvider::class);
    $reflection = new \ReflectionProperty($provider, 'packager');
    $reflection->setAccessible(true);

    /** @var Packager $packager */
    $packager = $reflection->getValue($provider);

    $mapping = $packager->getMigrationPublishMapping();

    expect($mapping)->toBeArray()
        ->toHaveCount(2);

    $destinations = array_values($mapping);
    $timestamps = [];

    foreach ($destinations as $dest) {
        $basename = basename($dest);
        preg_match('/^(\d{4}_\d{2}_\d{2}_\d{6})_/', $basename, $matches);
        expect($matches)->toHaveCount(2);
        $timestamps[] = $matches[1];
    }

    // Timestamps should be sequential (second >= first)
    expect($timestamps[1] >= $timestamps[0])->toBeTrue();
});
