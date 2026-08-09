<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use NyonCode\LaravelPackageToolkit\Packager;

trait PackageSeederStubPublishTest
{
    public function configure(Packager $packager): void
    {
        $packager->name('Test package')
            ->hasSeeders(['../stubs/MySeeder.stub']);
    }
}

uses(PackageSeederStubPublishTest::class);

afterEach(function () {
    if (file_exists(database_path('seeders/MySeeder.php'))) {
        @unlink(database_path('seeders/MySeeder.php'));
    }
});

test('a seeder shipped as a stub is published as a php file', function () {
    $this->artisan('vendor:publish --tag=test-package::seeders')->assertExitCode(0);

    expect(database_path('seeders/MySeeder.php'))->toBeFile()
        ->and(database_path('seeders/MySeeder.stub'))->not->toBeFile();
});
