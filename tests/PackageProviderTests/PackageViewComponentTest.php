<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use Blade;
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\Tests\TestPackageData\app\View\Components\Test;
use NyonCode\LaravelPackageToolkit\Tests\TestPackageData\app\View\Components2\Admin;
use NyonCode\LaravelPackageToolkit\Tests\TestPackageData\app\View\Components2\TestTwo;

trait PackageViewComponentTest
{

    public function configure(Packager $packager): void
    {
        $packager
            ->name('Test package')
            ->hasViews()
            ->hasComponent(
                'test',
                Test::class,
                't'
            )
            ->hasComponent(
                'admin',
                Admin::class,
                'panel'
            )->hasComponent(
                'admin',
                TestTwo::class,
            ) ;
    }
}

uses(PackageViewComponentTest::class);

test('can register a view component', function () {
    $view = Blade::render('<x-test-test name="Taylor" />');

    expect($view)->toBeString()->toContain('Taylor');
});

test('can register a view component with alias', function () {

    $view = Blade::render('<x-admin-panel name="Matthew" />');
    $view2 = Blade::render('<x-admin-admin name="Karl" />');
    $view3 = Blade::render('<x-admin-test-two >Hello</x-admin-test-two>');

    expect($view)
        ->toBeString()
        ->toContain('Matthew')
        ->and($view2)
        ->toBeString()
        ->toContain('Karl')
        ->and($view3)
        ->toBeString()
        ->toContain('Hello');
});