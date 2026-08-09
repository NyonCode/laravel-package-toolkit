<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\Support\Asset;
use NyonCode\LaravelPackageToolkit\Support\PackageAssets;
use ReflectionClass;

/**
 * The other half of discovery: what happens when the packager does name entries, against
 * the same realistic build directory the discovery test uses.
 *
 * Naming replaces discovery rather than adding to it, which is the mechanism by which the
 * two questions a directory listing cannot answer get answered — a code-split build's
 * entry points, and a bundle that is not a module. If naming merged with discovery
 * instead, neither would be expressible: the chunk would come back, and the IIFE would be
 * declared classic and discovered as a module in the same breath.
 */
trait PackageAssetDiscoveryOverrideTest
{
    public function configure(Packager $packager): void
    {
        $packager->name('Test Package')
            ->hasAssets('viteBuild', entries: [
                'css/theme.css',
                Asset::make('js/widget.mjs')->classic(),
                // Classic *and* given a Vite source below — the combination the skipped
                // test at the bottom of this file is about.
                Asset::make('js/app.js')->classic(),
            ])
            ->hasViteAssets(
                entries: ['resources/js/index.js' => 'js/app.js'],
                // The test package lives outside the Testbench application root, so the
                // prefix an installed package derives from its own location has to be
                // stated here.
                base: 'vendor/test-package',
            );
    }
}

uses(PackageAssetDiscoveryOverrideTest::class);

function forgetOverrideViteManifests(): void
{
    (new ReflectionClass(Vite::class))->getProperty('manifests')->setValue(null, []);
}

beforeEach(function () {
    File::deleteDirectory(public_path('build'));
    forgetOverrideViteManifests();
});

afterEach(function () {
    File::deleteDirectory(public_path('build'));
    forgetOverrideViteManifests();
});

test('naming entries replaces discovery instead of adding to it', function () {
    // `app.css` sits in the same directory and would have been discovered had nothing
    // been named. Order is declaration order, not discovery's alphabetical one.
    expect(array_keys(app(PackageAssets::class)->resolution('test-package')))
        ->toBe(['css/theme.css', 'js/widget.mjs', 'js/app.js']);
});

test('a named entry keeps the classic flag a discovered one could never carry', function () {
    $html = Blade::render('@packageScripts("test-package", "js/widget.mjs")');

    expect($html)->not->toContain('type="module"')
        ->and($html)->toContain(' defer');
});

test('a vite source attaches to a named shipped file without duplicating it', function () {
    $entries = app(PackageAssets::class)->resolution('test-package');

    expect($entries)->toHaveCount(3)
        ->and(array_keys($entries))->toBe(['css/theme.css', 'js/widget.mjs', 'js/app.js']);
});

/**
 * A regression, found while writing these tests and fixed in 2.4.1.
 *
 * `hasViteAssets()` replaces the earlier entry for the same shipped file — that is what
 * stops the file rendering twice — but the replacement used to be a fresh `Asset` carrying
 * none of the first one's presentation, so `->classic()` became a module again. It bit
 * precisely where the fallback matters: an application that did *not* build the entry got
 * the shipped IIFE emitted as `type="module"`, whose top-level declarations never reach
 * `window`.
 */
test('a vite entry keeps the presentation of the shipped file it replaces', function () {
    // No application manifest here, so this is the fallback path — the one that broke.
    $html = Blade::render('@packageScripts("test-package", "js/app.js")');

    expect($html)->toContain('vendor/test-package/js/app.js')
        ->and($html)->not->toContain('type="module"')
        ->and($html)->toContain(' defer');
});

test('the application build still wins for an entry that declares a source', function () {
    File::ensureDirectoryExists(public_path('build'));
    File::put(public_path('build/manifest.json'), json_encode([
        'vendor/test-package/resources/js/index.js' => [
            'file' => 'assets/index-abc123.js',
            'src' => 'vendor/test-package/resources/js/index.js',
            'isEntry' => true,
        ],
    ]));
    forgetOverrideViteManifests();

    $html = Blade::render('@packageAssets("test-package")');

    expect($html)->toContain('/build/assets/index-abc123.js')
        ->and($html)->not->toContain('vendor/test-package/js/app.js')
        // The stylesheet the application did not build still comes off the mirror.
        ->and($html)->toContain('vendor/test-package/css/theme.css')
        ->and(app(PackageAssets::class)->resolution('test-package'))->toBe([
            'css/theme.css' => 'shipped',
            'js/widget.mjs' => 'shipped',
            'js/app.js' => 'application build',
        ]);
});

test('the mirror is unaffected by which entries were named', function () {
    Blade::render('@packageStyles("test-package")');

    // Everything in the directory is still mirrored, named or not, tagged or not.
    expect(public_path('vendor/test-package/app.css'))->toBeFile()
        ->and(public_path('vendor/test-package/js/widget.mjs'))->toBeFile()
        ->and(public_path('vendor/test-package/assets/chunk-a1b2c3.js'))->toBeFile();
});
