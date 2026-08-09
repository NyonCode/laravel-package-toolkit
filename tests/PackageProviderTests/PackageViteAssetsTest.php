<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\Support\PackageAssets;
use ReflectionClass;

trait PackageViteAssetsTest
{
    public function configure(Packager $packager): void
    {
        $packager->name('Test Package')
            ->hasAbout()
            ->hasAssets(entries: ['css/index.css'])
            ->hasViteAssets(
                entries: ['resources/js/index.js' => 'js/index.js'],
                // The test package lives outside the Testbench application root, so the
                // prefix an installed package derives from its own location has to be
                // stated here — the same escape hatch a path repository needs.
                base: 'vendor/test-package',
            );
    }
}

uses(PackageViteAssetsTest::class);

/**
 * Laravel memoises parsed manifests in a static, which outlives the application a test
 * rebuilds — so a manifest written by one test would still answer for the next.
 */
function forgetViteManifests(): void
{
    (new ReflectionClass(Vite::class))->getProperty('manifests')->setValue(null, []);
}

function writeApplicationManifest(array $manifest): void
{
    File::ensureDirectoryExists(public_path('build'));
    File::put(public_path('build/manifest.json'), json_encode($manifest));

    forgetViteManifests();
}

beforeEach(function () {
    File::deleteDirectory(public_path('build'));
    forgetViteManifests();
});

afterEach(function () {
    File::deleteDirectory(public_path('build'));
    forgetViteManifests();
});

test('an entry the application built is served from the application manifest', function () {
    writeApplicationManifest([
        'vendor/test-package/resources/js/index.js' => [
            'file' => 'assets/index-abc123.js',
            'src' => 'vendor/test-package/resources/js/index.js',
            'isEntry' => true,
        ],
    ]);

    $html = Blade::render('@packageScripts("test-package")');

    expect($html)->toContain('/build/assets/index-abc123.js')
        ->and($html)->not->toContain('vendor/test-package/js/index.js');
});

test('an entry the application did not build falls back to the shipped file', function () {
    // A manifest exists, but the application never listed the package as an input.
    writeApplicationManifest([
        'resources/js/app.js' => [
            'file' => 'assets/app-000000.js',
            'src' => 'resources/js/app.js',
            'isEntry' => true,
        ],
    ]);

    $html = Blade::render('@packageScripts("test-package")');

    expect($html)->toContain(asset('vendor/test-package/js/index.js'))
        ->and($html)->not->toContain('/build/assets');
});

test('no manifest at all falls back to the shipped file', function () {
    $html = Blade::render('@packageScripts("test-package")');

    expect($html)->toContain(asset('vendor/test-package/js/index.js'))
        ->and(public_path('vendor/test-package/js/index.js'))->toBeFile();
});

test('the two modes mix per entry', function () {
    writeApplicationManifest([
        'vendor/test-package/resources/js/index.js' => [
            'file' => 'assets/index-abc123.js',
            'src' => 'vendor/test-package/resources/js/index.js',
            'isEntry' => true,
        ],
    ]);

    $html = Blade::render('@packageAssets("test-package")');

    expect($html)->toContain('/build/assets/index-abc123.js')
        ->and($html)->toContain(asset('vendor/test-package/css/index.css'));
});

test('a single url resolves through the application build too', function () {
    writeApplicationManifest([
        'vendor/test-package/resources/js/index.js' => [
            'file' => 'assets/index-abc123.js',
            'src' => 'vendor/test-package/resources/js/index.js',
            'isEntry' => true,
        ],
    ]);

    expect(Blade::render('@packageAssetUrl("test-package", "js/index.js")'))
        ->toBe(asset('build/assets/index-abc123.js'));
});

test('a running dev server serves the entry hot, ahead of any manifest', function () {
    writeApplicationManifest([
        'vendor/test-package/resources/js/index.js' => [
            'file' => 'assets/index-abc123.js',
            'src' => 'vendor/test-package/resources/js/index.js',
            'isEntry' => true,
        ],
    ]);

    File::put(public_path('hot'), 'http://localhost:5173');

    try {
        $html = Blade::render('@packageScripts("test-package")');
    } finally {
        File::delete(public_path('hot'));
    }

    expect($html)->toContain('http://localhost:5173/@vite/client')
        ->and($html)->toContain('http://localhost:5173/vendor/test-package/resources/js/index.js')
        ->and($html)->not->toContain('/build/assets');
});

test('resolution names how each entry actually resolves', function () {
    writeApplicationManifest([
        'vendor/test-package/resources/js/index.js' => [
            'file' => 'assets/index-abc123.js',
            'src' => 'vendor/test-package/resources/js/index.js',
            'isEntry' => true,
        ],
    ]);

    expect(app(PackageAssets::class)->resolution('test-package'))->toBe([
        'css/index.css' => 'shipped',
        'js/index.js' => 'application build',
    ]);
});

test('resolution names the silent case — a build that does not cover the package', function () {
    writeApplicationManifest([
        'resources/js/app.js' => [
            'file' => 'assets/app-000000.js',
            'src' => 'resources/js/app.js',
            'isEntry' => true,
        ],
    ]);

    expect(app(PackageAssets::class)->resolution('test-package')['js/index.js'])->toBe('shipped');
});

test('resolution reports nothing published and writes nothing to find out', function () {
    File::put(public_path('hot'), 'http://localhost:5173');

    try {
        $resolution = app(PackageAssets::class)->resolution('test-package');
    } finally {
        File::delete(public_path('hot'));
    }

    expect($resolution['js/index.js'])->toBe('dev server')
        // The mirror is lazy and reporting must not trigger it.
        ->and(public_path('vendor/test-package/js/index.js'))->not->toBeFile();
});

test('about names the resolution for a package that declares vite sources', function () {
    writeApplicationManifest([
        'vendor/test-package/resources/js/index.js' => [
            'file' => 'assets/index-abc123.js',
            'src' => 'vendor/test-package/resources/js/index.js',
            'isEntry' => true,
        ],
    ]);

    $this->artisan('about')
        ->expectsOutputToContain('js/index.js: application build')
        ->assertExitCode(0);
});

test('the shipped fallback is still published under the asset tags', function () {
    $this->artisan('vendor:publish --tag=test-package::assets')->assertExitCode(0);

    expect(public_path('vendor/test-package/js/index.js'))->toBeFile();
});
