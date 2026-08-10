<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use NyonCode\LaravelPackageToolkit\Packager;
use ReflectionClass;

/**
 * A running dev server, against a package whose stylesheet *and* script both declare a
 * Vite source — which is what it takes for both halves of a split layout to render a
 * block of Laravel's own Vite markup, and the only shape where the question below comes
 * up at all.
 */
trait PackageAssetHotModeTest
{
    public function configure(Packager $packager): void
    {
        $packager->name('Test Package')
            ->hasAssets(entries: ['css/index.css', 'js/index.js'])
            ->hasViteAssets(
                entries: [
                    'resources/css/index.css' => 'css/index.css',
                    'resources/js/index.js' => 'js/index.js',
                ],
                // The test package lives outside the Testbench application root.
                base: 'vendor/test-package',
            );
    }
}

uses(PackageAssetHotModeTest::class);

function forgetHotViteManifests(): void
{
    (new ReflectionClass(Vite::class))->getProperty('manifests')->setValue(null, []);
}

beforeEach(function () {
    File::deleteDirectory(public_path('build'));
    forgetHotViteManifests();
});

afterEach(function () {
    File::delete(public_path('hot'));
    File::deleteDirectory(public_path('build'));
    forgetHotViteManifests();
});

function withDevServer(callable $callback): mixed
{
    File::put(public_path('hot'), 'http://localhost:5173');

    try {
        return $callback();
    } finally {
        File::delete(public_path('hot'));
    }
}

test('both halves of a declaration are served hot, and neither touches the mirror', function () {
    [$head, $body] = withDevServer(fn () => [
        Blade::render('@packageStyles("test-package")'),
        Blade::render('@packageScripts("test-package")'),
    ]);

    expect($head)->toContain('http://localhost:5173/vendor/test-package/resources/css/index.css')
        ->and($body)->toContain('http://localhost:5173/vendor/test-package/resources/js/index.js')
        ->and($head)->not->toContain('vendor/test-package/css/index.css?id=')
        ->and(public_path('vendor/test-package/css/index.css'))->not->toBeFile();
});

/**
 * A layout that puts its stylesheets in `<head>` and its scripts at the end of `<body>`
 * makes two calls, and Laravel's Vite prepends the dev-server client to every block it
 * renders — so the client tag appears in both.
 *
 * That is a redundant tag, not a second client. Both carry `type="module"` and the same
 * URL, and a module URL is fetched and evaluated once per document however many script
 * tags name it, so exactly one HMR client connects either way.
 *
 * Suppressing the second would mean remembering across a request that the first was
 * emitted, and getting that reset wrong under a long-lived worker costs the client
 * altogether — no HMR, on the one setup where HMR is the point. A duplicate tag in a dev
 * response is the cheaper of the two by a wide margin, so it stays.
 */
test('a split layout emits the dev-server client in each block, as one module', function () {
    [$head, $body] = withDevServer(fn () => [
        Blade::render('@packageStyles("test-package")'),
        Blade::render('@packageScripts("test-package")'),
    ]);

    $client = 'http://localhost:5173/@vite/client';

    expect(substr_count($head, $client))->toBe(1)
        ->and(substr_count($body, $client))->toBe(1)
        // Identical URL and `type="module"` in both — which is what makes the duplicate
        // inert rather than a second connection.
        ->and($head)->toContain('<script type="module" src="'.$client.'"')
        ->and($body)->toContain('<script type="module" src="'.$client.'"');
});

test('the whole-set form emits it once, since it is one call', function () {
    $html = withDevServer(fn () => Blade::render('@packageAssets("test-package")'));

    expect(substr_count($html, 'http://localhost:5173/@vite/client'))->toBe(1)
        ->and($html)->toContain('resources/css/index.css')
        ->and($html)->toContain('resources/js/index.js');
});
