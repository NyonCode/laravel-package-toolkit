<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use NyonCode\LaravelPackageToolkit\Packager;

trait PackageFactoriesPublishTest
{
    public function configure(Packager $packager): void
    {
        $packager->name('Test package')
            ->hasFactories();
    }
}

uses(PackageFactoriesPublishTest::class);

afterEach(function () {
    if (file_exists(database_path('factories/TestModelFactory.php'))) {
        @unlink(database_path('factories/TestModelFactory.php'));
    }
});

test('can publish the package factories', function () {
    $this->artisan('vendor:publish --tag=test-package::factories')->assertExitCode(0);

    expect(database_path('factories/TestModelFactory.php'))->toBeFile()
        ->and(file_get_contents(database_path('factories/TestModelFactory.php')))
        ->toContain('namespace Database\Factories;');
});
