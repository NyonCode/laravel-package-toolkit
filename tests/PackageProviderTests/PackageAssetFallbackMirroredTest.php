<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\Support\Asset;
use NyonCode\LaravelPackageToolkit\Support\PackageAssets;

/**
 * `hasAssetFallback()` on a mirrored package — which is to say on the default, since
 * `hasAssets()` turns mirroring on unless told otherwise.
 *
 * {@see PackageAssetFallbackTest} covers the same branch with the mirror off, where the
 * two arrive at it identically as far as the *rendering* is concerned. What only this
 * shape can show is {@see PackageAssets::resolution()}: a mirrored package is the one
 * whose published copy the resolver would create on demand, so saying whether an entry
 * is served from `public/` means asking whether that copy could still be made. An
 * unwritable `public/` is where it cannot, and where a report of `shipped` would be
 * exactly wrong — the page is on the fallback, or on nothing at all.
 *
 * So `public/` really is made unwritable here rather than simulated, and the tests that
 * need that are skipped where the platform does not enforce permission bits.
 */
trait PackageAssetFallbackMirroredTest
{
    public function configure(Packager $packager): void
    {
        $packager->name('Test Package')
            ->hasAssets(entries: ['css/index.css', Asset::make('js/legacy.js')->classic()])
            ->hasAssetFallback(fn (string $file, string $package): string => "/served/$package/$file");
    }
}

uses(PackageAssetFallbackMirroredTest::class);

/**
 * Run something with `public/vendor` unwritable, so the mirror cannot create the
 * package's directory inside it.
 *
 * `public/` itself is left alone: a deployment that ships a read-only `public/vendor`
 * under a writable `public/` is the shape that walking up to the nearest existing
 * ancestor exists for, and it is the harder of the two to get right.
 */
function withUnwritableVendorDirectory(callable $callback): mixed
{
    $vendor = public_path('vendor');

    File::deleteDirectory(public_path('vendor/test-package'));
    File::ensureDirectoryExists($vendor);
    chmod($vendor, 0o555);

    try {
        return $callback();
    } finally {
        chmod($vendor, 0o755);
    }
}

test('an entry the mirror has not reached yet still reports shipped', function () {
    // The mirror is lazy and `about` runs in the console, so on a fresh install nothing
    // is published at the moment this is asked. That is not a problem to report.
    expect(public_path('vendor/test-package/css/index.css'))->not->toBeFile()
        ->and(app(PackageAssets::class)->resolution('test-package'))->toBe([
            'css/index.css' => 'shipped',
            'js/legacy.js' => 'shipped',
        ]);
});

test('an unwritable public names the fallback the page is actually served from', function () {
    [$html, $resolution] = withUnwritableVendorDirectory(fn () => [
        Blade::render('@packageAssets("test-package")'),
        app(PackageAssets::class)->resolution('test-package'),
    ]);

    expect($resolution)->toBe([
        'css/index.css' => 'fallback',
        'js/legacy.js' => 'fallback',
    ])
        ->and($html)->toContain('/served/test-package/css/index.css')
        ->and(public_path('vendor/test-package/css/index.css'))->not->toBeFile();
})->skip(fn () => ! permissionsAreEnforced(), 'Permission bits are not enforced here.');

test('an unwritable public with nothing to fall back on reports nothing published', function () {
    app(PackageAssets::class)->declare(
        package: 'no-fallback-package',
        directory: __DIR__.'/../TestPackageData/dist',
        entries: [Asset::make('js/index.js')],
        base: null,
        mirrored: true,
    );

    $resolution = withUnwritableVendorDirectory(
        fn () => app(PackageAssets::class)->resolution('no-fallback-package')
    );

    expect($resolution)->toBe(['js/index.js' => 'not published']);
})->skip(fn () => ! permissionsAreEnforced(), 'Permission bits are not enforced here.');

test('a copy already published reports shipped, whatever can be written now', function () {
    $this->artisan('vendor:publish --tag=test-package::assets')->assertExitCode(0);

    $resolution = withUnwritablePublishedVendorDirectory(
        fn () => app(PackageAssets::class)->resolution('test-package')
    );

    expect($resolution)->toBe([
        'css/index.css' => 'shipped',
        'js/legacy.js' => 'shipped',
    ]);
})->skip(fn () => ! permissionsAreEnforced(), 'Permission bits are not enforced here.');

/**
 * The same as {@see withUnwritableVendorDirectory()}, minus the delete — for the case
 * where what is under test is a copy that was published before `public/` closed.
 */
function withUnwritablePublishedVendorDirectory(callable $callback): mixed
{
    $vendor = public_path('vendor');

    chmod($vendor, 0o555);

    try {
        return $callback();
    } finally {
        chmod($vendor, 0o755);
    }
}
