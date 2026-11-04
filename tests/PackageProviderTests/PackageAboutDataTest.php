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

        TestServiceProvider::$aboutDataUsing = function (): array {
            PackageAboutDataCalls::$count++;

            return [
                'Repository' => 'https://github.com/nyoncode/laravel-package-toolkit',
                'Author' => 'NyonCode',
            ];
        };
    }
}

/** How often the provider's about data was asked for. */
final class PackageAboutDataCalls
{
    public static int $count = 0;
}

uses(PackageAboutDataTest::class);

beforeEach(fn () => PackageAboutDataCalls::$count = 0);

test('does not ask the provider for its about data until about runs', function () {
    // The application has booted every provider by now: nothing may have been resolved.
    expect(PackageAboutDataCalls::$count)->toBe(0);

    Artisan::call('about', ['--only' => 'About Test']);

    expect(PackageAboutDataCalls::$count)->toBe(1);
});

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
