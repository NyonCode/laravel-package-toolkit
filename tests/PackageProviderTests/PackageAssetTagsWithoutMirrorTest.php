<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use Illuminate\Support\Facades\Blade;
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\Support\PackageAssets;

/**
 * Without the mirror the tags come from whatever `vendor:publish` left in `public/`,
 * so the same package renders nothing before it is published and a cache-busted tag
 * afterwards.
 */
trait PackageAssetTagsWithoutMirrorTest
{
    public function configure(Packager $packager): void
    {
        $packager->name('Test Package')
            ->hasAssets(mirror: false, entries: ['css/index.css', 'js/index.js']);
    }
}

uses(PackageAssetTagsWithoutMirrorTest::class);

test('nothing is rendered while the assets are unpublished', function () {
    expect(trim(Blade::render('@packageAssets("test-package")')))->toBe('');
});

test('the resolution is reported as not published', function () {
    expect(app(PackageAssets::class)->resolution('test-package'))->toBe([
        'css/index.css' => 'not published',
        'js/index.js' => 'not published',
    ]);
});

test('a published asset resolves as shipped', function () {
    $this->artisan('vendor:publish --tag=test-package::assets')->assertExitCode(0);

    expect(app(PackageAssets::class)->resolution('test-package'))->toBe([
        'css/index.css' => 'shipped',
        'js/index.js' => 'shipped',
    ]);
});

test('the url helper answers for a published entry only', function () {
    expect(app(PackageAssets::class)->url('test-package', 'css/index.css'))->toBeNull();

    $this->artisan('vendor:publish --tag=test-package::assets')->assertExitCode(0);

    expect(app(PackageAssets::class)->url('test-package', 'css/index.css'))
        ->toContain('vendor/test-package/css/index.css?id=');
});

test('publishing brings the tags back, cache busted', function () {
    $this->artisan('vendor:publish --tag=test-package::assets')->assertExitCode(0);

    $html = Blade::render('@packageAssets("test-package")');

    expect($html)->toContain('vendor/test-package/css/index.css?id=')
        ->and($html)->toContain('vendor/test-package/js/index.js?id=');
});
