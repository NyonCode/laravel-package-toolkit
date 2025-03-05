<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use Blade;
use NyonCode\LaravelPackageToolkit\Packager;

trait PackageViewComponentNamespacesTest
{

    public function configure(Packager $packager): void
    {
        $packager->name('Test package')
            ->hasViews()
            ->hasComponentNamespaces([
                'admin' => 'NyonCode\LaravelPackageToolkit\Tests\TestPackageData\app\View\Components2',
                'test' => 'NyonCode\LaravelPackageToolkit\Tests\TestPackageData\app\View\Components',
            ]);

    }
}

uses(PackageViewComponentNamespacesTest::class);

test('can register a view component with namespaces', function () {

    $view = Blade::render('<x-test::test name="Taylor" />');
    $view2 = Blade::render('<x-admin::admin name="Donald" />');
    $view3 = Blade::render('<x-admin::test-two>Hello</x-admin::test-two>');

    expect($view)->toBeString()
        ->and($view)->toContain('Taylor')
        ->and($view2)->toBeString()
        ->and($view2)->toContain('Donald')
        ->and($view3)->toBeString()
        ->and($view3)->toContain('Hello')
    ;
});

test('publishes the view component namespaces', function () {
    $this->artisan('vendor:publish --tag=test-package::view-component-namespaces')->assertExitCode(0);
});
