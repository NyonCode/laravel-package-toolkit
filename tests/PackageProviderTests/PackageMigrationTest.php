<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use Exception;
use File;
use NyonCode\LaravelPackageToolkit\Packager;

trait PackageMigrationTest
{

    public function configure(Packager $package): void
    {
        $package->name('Package test')->hasMigrations();
    }
}

uses(PackageMigrationTest::class);

test(
    'Package test migrations are published',
    function () {

        $filesExist = false;

        $this->artisan('vendor:publish', ['--tag' => 'package-test::migrations'])
            ->assertExitCode(0);

        $packageMigrationFiles = File::allFiles(__DIR__ . '/../TestPackageData/database/migrations');

        foreach ($packageMigrationFiles as $file) {
            if(file_exists(database_path('migrations/' . $file->getFilename()))) {
                $filesExist = true;
            }
        }

        expect($filesExist)->toBeTrue();
    }
);

