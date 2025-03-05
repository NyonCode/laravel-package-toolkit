<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use Illuminate\Support\Facades\Blade;
use NyonCode\LaravelPackageToolkit\Packager;

trait PackageViewComposersTest
{
    public function configure(Packager $packager): void
    {
        $packager
            ->name('Test Package')
            ->hasViews()
            ->hasViewComposer('*', function ($view) {
                $view->with('testShareData', 'test-value');
            });
    }
}

uses(PackageViewComposersTest::class);

test('can register a view composer', function () {
    $view = view('test-package::shared-data')->render();
    $view2 = view('test-package::shared-data-two')->render();
    expect($view)
        ->toBeString()
        ->toContain('test-value')
        ->and($view2)
        ->toBeString()
        ->toContain('test-value');
});
