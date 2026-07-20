<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use Illuminate\Support\Facades\File;
use NyonCode\LaravelPackageToolkit\Packager;

trait PackageRoutesPublishTest
{
    public function configure(Packager $packager): void
    {
        $packager->name('Test Package')
            ->hasRoutes();
    }
}

uses(PackageRoutesPublishTest::class);

test('can publish route files', function () {
    $this->artisan('vendor:publish --tag=test-package::routes')->assertExitCode(0);

    expect(base_path('routes/vendor/test-package/test.php'))->toBeFile()
        ->and(base_path('routes/vendor/test-package/foo.php'))->toBeFile();

    File::deleteDirectory(base_path('routes/vendor/test-package'));
});
