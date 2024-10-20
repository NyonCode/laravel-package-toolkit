<?php

namespace NyonCode\LaravelPackageBuilder\Tests\PackageProviderTests;

use NyonCode\LaravelPackageBuilder\Exceptions\PackagerException;
use NyonCode\LaravelPackageBuilder\Packager;

trait PackageViewsTest
{
    /**
     * @throws PackagerException
     */
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
