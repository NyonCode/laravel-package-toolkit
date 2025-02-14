<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use NyonCode\LaravelPackageToolkit\Packager;

trait PackageViewComponentNamespaceTest
{
    public function configure(Packager $packager): void
    {
        $packager->name('Test package')
            ->hasViews()
            ->hasComponentNamespace(
                prefix: 'test',
                namespace: 'NyonCode\LaravelPackageToolkit\Tests\TestPackageData\app\View\Components'
            );
    }
}

uses(PackageViewComponentNamespaceTest::class);

test(
    'can register a view component with a namespace',
    function () {
        $view = \Blade::render('<x-test::test name="Taylor" />');

        expect($view)->toBeString()
            ->and($view)->toContain('Taylor');
    }
);