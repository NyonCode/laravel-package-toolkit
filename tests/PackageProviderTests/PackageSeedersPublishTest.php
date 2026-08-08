<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use NyonCode\LaravelPackageToolkit\Packager;

trait PackageSeedersPublishTest
{
    public function configure(Packager $packager): void
    {
        $packager->name('Test package')
            ->hasSeeders();
    }
}

uses(PackageSeedersPublishTest::class);

afterEach(function () {
    foreach (['TestSeeder.php', 'SecondTestSeeder.php'] as $seeder) {
        if (file_exists(database_path('seeders/'.$seeder))) {
            @unlink(database_path('seeders/'.$seeder));
        }
    }
});

test('can publish the package seeders', function () {
    $this->artisan('vendor:publish --tag=test-package::seeders')->assertExitCode(0);

    expect(database_path('seeders/TestSeeder.php'))->toBeFile()
        ->and(database_path('seeders/SecondTestSeeder.php'))->toBeFile();
});

test('seeders are published flat so the application seeder namespace resolves them', function () {
    $this->artisan('vendor:publish --tag=test-package::seeders')->assertExitCode(0);

    expect(file_get_contents(database_path('seeders/TestSeeder.php')))
        ->toContain('namespace Database\Seeders;')
        ->and(database_path('seeders/vendor/test-package'))->not->toBeDirectory();
});
