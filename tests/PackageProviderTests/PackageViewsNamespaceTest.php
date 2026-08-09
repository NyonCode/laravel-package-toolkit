<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use NyonCode\LaravelPackageToolkit\Packager;

trait PackageViewsNamespaceTest
{
    public function configure(Packager $packager): void
    {
        $packager->name('Test package')
            ->hasViews(
                viewsPath: __DIR__.'/../TestPackageData/resources/views',
                namespace: 'custom-views'
            );
    }
}

uses(PackageViewsNamespaceTest::class);

test('views declared by an absolute path are registered', function () {
    expect(view('test-package::test-page')->render())->toContain('Hello world');
});

test('views declared by an absolute path publish', function () {
    $this->artisan('vendor:publish --tag=test-package::views')->assertExitCode(0);

    expect(resource_path('views/vendor/test-package/test-page.blade.php'))->toBeFile();
});

/**
 * The `$namespace` argument is accepted and stored, but nothing reads it back:
 * `bootViews()` and `publishViews()` both use the package short name. This pins the
 * behaviour as it stands so that giving the argument meaning is a deliberate change.
 */
test('the namespace argument does not currently replace the short name', function () {
    expect(fn () => view('custom-views::test-page')->render())
        ->toThrow(\InvalidArgumentException::class);
});
