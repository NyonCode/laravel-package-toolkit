<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use Illuminate\Support\Facades\File;
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\Tests\TestPackageData\app\View\Components2\Admin;
use NyonCode\LaravelPackageToolkit\Tests\TestPackageData\app\View\Components2\TestTwo;
use NyonCode\LaravelPackageToolkit\Tests\TestPackageData\app\View\Components2\Three;
use ReflectionException;

trait PackagerViewComponentsPublishTest
{
    /**
     * @throws ReflectionException
     */
    public function configure(Packager $packager): void
    {
        $packager
            ->name('Test package')
            ->hasViews()
            ->hasComponents(
                'test',
                'NyonCode\LaravelPackageToolkit\Tests\TestPackageData\app\View\Components\Test'
            )
            ->hasComponents(
                prefix: 'admin',
                components: ['panel' => Admin::class, 'two' => TestTwo::class, Three::class]
            );
    }
}

uses(PackagerViewComponentsPublishTest::class);

test('can publish view components', function () {
    $this->artisan('vendor:publish --tag=test-package::view-components')
        ->assertSuccessful();

    expect(base_path('app/View/Components/test-package/Components/Test.php'))->toBeFile()
        ->and(base_path('app/View/Components/test-package/Components2/Admin.php'))->toBeFile();

    File::deleteDirectory(base_path('app/View/Components/test-package'));
});
