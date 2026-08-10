<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use Illuminate\Support\Facades\Blade;
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\Support\Asset;
use NyonCode\LaravelPackageToolkit\Support\PackageAssets;

/**
 * `hasAssetFallback()` — what a package renders when nothing is published.
 *
 * The mirror is off rather than `public/` being made unwritable, because the two reach
 * the same branch and only one of them needs permission bits the platform may not
 * enforce. What is under test is the branch, not how it was arrived at.
 */
trait PackageAssetFallbackTest
{
    public static int $calls = 0;

    public function configure(Packager $packager): void
    {
        self::$calls = 0;

        $packager->name('Test Package')
            ->hasAssets(mirror: false, entries: [
                'css/index.css',
                Asset::make('js/legacy.js')->classic()->attributes(['data-legacy' => true]),
            ])
            ->hasAssetFallback(function (string $file, string $package): string {
                self::$calls++;

                return "/served/$package/$file?id=7";
            });
    }
}

uses(PackageAssetFallbackTest::class);

test('an unpublished entry renders from the fallback instead of vanishing', function () {
    $html = Blade::render('@packageAssets("test-package")');

    expect($html)->toContain('<link rel="stylesheet" href="/served/test-package/css/index.css?id=7"')
        ->and($html)->toContain('<script src="/served/test-package/js/legacy.js?id=7"');
});

test('the tag keeps everything the declaration said about it', function () {
    $html = Blade::render('@packageScripts("test-package", "js/legacy.js")');

    expect($html)->not->toContain('type="module"')
        ->and($html)->toContain(' defer')
        ->and($html)->toContain('data-legacy')
        ->and($html)->toContain('data-navigate-track="reload"');
});

test('the url helper answers from the fallback as well', function () {
    expect(app(PackageAssets::class)->url('test-package', 'css/index.css'))
        ->toBe('/served/test-package/css/index.css?id=7');
});

test('the resolution names it, rather than reporting nothing is published', function () {
    expect(app(PackageAssets::class)->resolution('test-package'))->toBe([
        'css/index.css' => 'fallback',
        'js/legacy.js' => 'fallback',
    ]);
});

test('a published copy wins, and the resolver is never called', function () {
    $this->artisan('vendor:publish --tag=test-package::assets')->assertExitCode(0);

    $html = Blade::render('@packageAssets("test-package")');

    expect($html)->toContain('vendor/test-package/css/index.css?id=')
        ->and($html)->not->toContain('/served/')
        ->and(PackageAssetFallbackTest::$calls)->toBe(0);
});

test('a resolver with nothing to offer drops the tag as before', function () {
    app(PackageAssets::class)->declare(
        package: 'empty-fallback',
        directory: __DIR__.'/../TestPackageData/dist',
        entries: [Asset::make('js/index.js')],
        base: null,
        mirrored: false,
        fallback: fn (): ?string => null,
    );

    expect(trim(Blade::render('@packageAssets("empty-fallback")')))->toBe('')
        ->and(app(PackageAssets::class)->resolution('empty-fallback'))
        ->toBe(['js/index.js' => 'not published']);
});

test('a package that declared no fallback is unchanged', function () {
    app(PackageAssets::class)->declare(
        package: 'no-fallback',
        directory: __DIR__.'/../TestPackageData/dist',
        entries: [Asset::make('js/index.js')],
        base: null,
        mirrored: false,
    );

    expect(trim(Blade::render('@packageAssets("no-fallback")')))->toBe('')
        ->and(app(PackageAssets::class)->url('no-fallback', 'js/index.js'))->toBeNull();
});
