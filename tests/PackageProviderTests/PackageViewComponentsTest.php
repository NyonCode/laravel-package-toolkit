<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use Blade;
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\Tests\TestPackageData\app\View\Components2\Admin;
use NyonCode\LaravelPackageToolkit\Tests\TestPackageData\app\View\Components2\TestTwo;
use NyonCode\LaravelPackageToolkit\Tests\TestPackageData\app\View\Components2\Three;

trait PackageViewComponentsTest
{
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
                components: [
                    'panel' => Admin::class,
                    'two' => TestTwo::class,
                    Three::class,
                ]
            );
    }
}

uses(PackageViewComponentsTest::class);

test('can register a view components', function () {

    $view = Blade::render('<x-test-test name="Taylor" />');
    $view2 = Blade::render('<x-admin-test-two>Test content</x-admin-test-two>');
    $view3 = Blade::render('<x-admin-panel name="Karl" />');
    $view4 = Blade::render('<x-admin-admin name="Dom" />');
    $view5 = Blade::render('<x-admin-two>Say hello</x-admin-two>');
    $view6 = Blade::render('<x-admin-three name="John" />');

    expect($view)
        ->toBeString()
        ->toContain('Taylor')
        ->and($view2)
        ->toBeString()
        ->toContain('Test content')
        ->and($view3)
        ->toBeString()
        ->toContain('Karl')
        ->and($view4)
        ->toBeString()
        ->toContain('Dom')
        ->and($view5)
        ->toBeString()
        ->toContain('Say hello')
        ->and($view6)
        ->toBeString()
        ->toContain('John');
});
