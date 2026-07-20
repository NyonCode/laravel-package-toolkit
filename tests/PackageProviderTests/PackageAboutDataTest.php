<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use Illuminate\Support\Facades\Artisan;
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\Tests\TestPackageData\src\TestServiceProvider;

trait PackageAboutDataTest
{
    public function configure(Packager $packager): void
    {
        $packager->name('About Test')->hasAbout()->hasVersion('9.9.9');

        TestServiceProvider::$aboutDataUsing = fn (): array => [
            'Repository' => 'https://github.com/nyoncode/laravel-package-toolkit',
            'Author' => 'NyonCode',
        ];
    }
}

uses(PackageAboutDataTest::class);

test('merges custom about data from the service provider with the version', function () {
    Artisan::call('about', ['--only' => 'About Test']);
    $output = Artisan::output();

    expect($output)
        ->toContain('Version')
        ->toContain('9.9.9')
        ->toContain('Repository')
        ->toContain('https://github.com/nyoncode/laravel-package-toolkit')
        ->toContain('Author')
        ->toContain('NyonCode');
});
