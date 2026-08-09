<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use Illuminate\Support\Facades\Blade;
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\Support\PackageAssets;

/**
 * Discovery against a realistic build output rather than a directory holding only what
 * the assertion wants to find.
 *
 * `tests/TestPackageData/viteBuild` is shaped like something a bundler actually emits — a
 * chunk directory, source maps, a font, an image, a manifest — because every one of those
 * is a way discovery can go wrong quietly. A chunk handed its own `<script>` runs twice,
 * a `.map` turned into a script 404s in the console, and neither shows up in a test whose
 * fixture contains two clean files.
 *
 *   viteBuild/
 *   ├── app.css                    ✓ root-level stylesheet
 *   ├── manifest.json              ✗ not an asset extension
 *   ├── assets/chunk-a1b2c3.js     ✗ chunk directory, not walked
 *   ├── assets/vendor-d4e5f6.css   ✗
 *   ├── css/theme.css              ✓
 *   ├── css/theme.css.map          ✗ source map
 *   ├── css/inter.woff2            ✗ font
 *   ├── img/logo.png               ✗ neither an asset extension nor a walked directory
 *   ├── js/app.js                  ✓
 *   ├── js/app.js.map              ✗
 *   └── js/widget.mjs              ✓ `.mjs` is on the allowlist
 */
trait PackageAssetDiscoveryTest
{
    public function configure(Packager $packager): void
    {
        // No entries named: the asset directory answers for itself.
        $packager->name('Test Package')
            ->hasAssets('viteBuild');
    }
}

uses(PackageAssetDiscoveryTest::class);

/** The four entries the fixture should yield, in the order discovery sorts them. */
const DISCOVERED = ['app.css', 'css/theme.css', 'js/app.js', 'js/widget.mjs'];

// What is registered
test('discovery registers exactly the entries a template can render, sorted', function () {
    expect(app(PackageAssets::class)->declared('test-package'))->toBeTrue()
        ->and(array_keys(app(PackageAssets::class)->resolution('test-package')))
        ->toBe(DISCOVERED);
});

test('a chunk directory is left to the bundler', function () {
    $entries = array_keys(app(PackageAssets::class)->resolution('test-package'));

    expect($entries)->not->toContain('assets/chunk-a1b2c3.js')
        ->and($entries)->not->toContain('assets/vendor-d4e5f6.css');
});

test('source maps, fonts, images and a manifest are not assets to render', function () {
    $entries = array_keys(app(PackageAssets::class)->resolution('test-package'));

    expect($entries)->not->toContain('css/theme.css.map')
        ->and($entries)->not->toContain('js/app.js.map')
        ->and($entries)->not->toContain('css/inter.woff2')
        ->and($entries)->not->toContain('img/logo.png')
        ->and($entries)->not->toContain('manifest.json');
});

test('a stylesheet at the directory root is found, not only one under css/', function () {
    expect(array_keys(app(PackageAssets::class)->resolution('test-package')))
        ->toContain('app.css');
});

// What is rendered
test('every discovered entry renders, stylesheets before scripts', function () {
    $html = Blade::render('@packageAssets("test-package")');

    expect(substr_count($html, '<link rel="stylesheet"'))->toBe(2)
        ->and(substr_count($html, '<script'))->toBe(2)
        ->and(strpos($html, '<link'))->toBeLessThan(strpos($html, '<script'))
        ->and($html)->toContain('vendor/test-package/app.css')
        ->and($html)->toContain('vendor/test-package/css/theme.css')
        ->and($html)->toContain('vendor/test-package/js/app.js')
        ->and($html)->toContain('vendor/test-package/js/widget.mjs');
});

test('nothing that was skipped reaches the markup', function () {
    $html = Blade::render('@packageAssets("test-package")');

    expect($html)->not->toContain('chunk-a1b2c3')
        ->and($html)->not->toContain('vendor-d4e5f6')
        ->and($html)->not->toContain('.map')
        ->and($html)->not->toContain('woff2')
        ->and($html)->not->toContain('logo.png')
        ->and($html)->not->toContain('manifest.json');
});

test('a discovered script is a module and stays navigate-tracked', function () {
    $html = Blade::render('@packageScripts("test-package")');

    expect(substr_count($html, 'type="module"'))->toBe(2)
        ->and(substr_count($html, 'data-navigate-track="reload"'))->toBe(2)
        ->and($html)->not->toContain(' defer');
});

test('a discovered tag points at the mirrored copy, cache-busted by its mtime', function () {
    $html = Blade::render('@packageStyles("test-package", "css/theme.css")');

    $published = public_path('vendor/test-package/css/theme.css');

    expect($published)->toBeFile()
        ->and($html)->toBe(
            '<link rel="stylesheet" href="'
            .asset('vendor/test-package/css/theme.css').'?id='.filemtime($published)
            .'" data-navigate-track="reload">'
        );
});

test('a discovered entry can be named like any other', function () {
    $html = Blade::render('@packageAssets("test-package", "js/widget.mjs")');

    expect($html)->toContain('js/widget.mjs')
        ->and($html)->not->toContain('js/app.js')
        ->and(Blade::render('@packageAssetUrl("test-package", "app.css")'))
        ->toStartWith(asset('vendor/test-package/app.css').'?id=');
});

// The mirror underneath
test('the mirror still copies what discovery declined to tag', function () {
    // Resolving one URL syncs the whole directory, which is the point: `js/app.js`
    // imports the chunk, and the browser fetches that itself. A mirror that copied only
    // what got a tag would leave a dangling import behind.
    Blade::render('@packageScripts("test-package")');

    expect(public_path('vendor/test-package/assets/chunk-a1b2c3.js'))->toBeFile()
        ->and(public_path('vendor/test-package/assets/vendor-d4e5f6.css'))->toBeFile()
        ->and(public_path('vendor/test-package/img/logo.png'))->toBeFile()
        ->and(public_path('vendor/test-package/css/inter.woff2'))->toBeFile()
        ->and(public_path('vendor/test-package/css/theme.css.map'))->toBeFile()
        ->and(public_path('vendor/test-package/manifest.json'))->toBeFile();
});

test('every discovered entry resolves to the shipped copy with no application build', function () {
    expect(app(PackageAssets::class)->resolution('test-package'))->toBe([
        'app.css' => 'shipped',
        'css/theme.css' => 'shipped',
        'js/app.js' => 'shipped',
        'js/widget.mjs' => 'shipped',
    ]);
});

test('discovery does not disturb publishing', function () {
    $this->artisan('vendor:publish --tag=test-package::assets')->assertExitCode(0);

    expect(public_path('vendor/test-package/js/app.js'))->toBeFile()
        ->and(public_path('vendor/test-package/assets/chunk-a1b2c3.js'))->toBeFile();
});
