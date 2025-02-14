<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use NyonCode\LaravelPackageToolkit\Packager;

trait PackageViewsTest
{

    public function configure(Packager $packager): void
    {
        $packager->name('Test package')->hasViews();
    }
}

uses(PackageViewsTest::class);

test('can register a view', fn() => expect(view('test-package::test-page')->render())->toContain('Hello world'));

test('can publish the views file', function () {
    $this->artisan('vendor:publish --tag=test-package::views')->assertExitCode(0);
    expect(resource_path('views/vendor/test-package/test-page.blade.php'))->toBeFile();
});
