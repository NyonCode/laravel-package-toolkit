<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\Blade;
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\Support\Asset;
use NyonCode\LaravelPackageToolkit\Support\PackageAssets;

trait PackageAssetTagsTest
{
    public function configure(Packager $packager): void
    {
        $packager->name('Test Package')
            ->hasAssets(entries: [
                'css/index.css',
                'js/index.js',
                Asset::make('js/legacy.js')
                    ->classic()
                    ->attributes(['data-legacy' => true, 'nonce' => 'declared']),
            ]);
    }
}

uses(PackageAssetTagsTest::class);

test('the asset directives are registered', function () {
    expect(Blade::getCustomDirectives())
        ->toHaveKeys(['packageAssets', 'packageStyles', 'packageScripts', 'packageAssetUrl'])
        // One character from `packageAssets`, and a bare URL where tags were meant.
        ->and(Blade::getCustomDirectives())->not->toHaveKey('packageAsset');
});

test('a csp nonce set on the application reaches the tags the toolkit renders itself', function () {
    app(Vite::class)->useCspNonce('n0nc3');

    $html = Blade::render('@packageAssets("test-package")');

    // Both tags without a nonce of their own — the third entry declares its own.
    expect(substr_count($html, 'nonce="n0nc3"'))->toBe(2)
        ->and(substr_count($html, 'nonce='))->toBe(3);
});

test('an explicitly declared nonce is left alone', function () {
    app(Vite::class)->useCspNonce('n0nc3');

    $html = Blade::render('@packageScripts("test-package", "js/legacy.js")');

    expect($html)->toContain('nonce="declared"')
        ->and($html)->not->toContain('n0nc3');
});

test('a declared asset renders a tag pointing at the mirrored copy', function () {
    $html = Blade::render('@packageStyles("test-package")');

    $published = public_path('vendor/test-package/css/index.css');

    expect($published)->toBeFile()
        ->and($html)->toBe(
            '<link rel="stylesheet" href="'
            .asset('vendor/test-package/css/index.css').'?id='.filemtime($published)
            .'" data-navigate-track="reload">'
        );
});

test('scripts render as modules by default', function () {
    $html = Blade::render('@packageScripts("test-package", "js/index.js")');

    expect($html)->toContain('<script src="'.asset('vendor/test-package/js/index.js'))
        ->and($html)->toContain('type="module"')
        ->and($html)->toContain('data-navigate-track="reload"');
});

test('a classic script opts out of type=module and defers instead', function () {
    $html = Blade::render('@packageScripts("test-package", "js/legacy.js")');

    expect($html)->not->toContain('type="module"')
        ->and($html)->toContain(' defer')
        ->and($html)->toContain('data-legacy');
});

test('the full set renders stylesheets before scripts', function () {
    $html = Blade::render('@packageAssets("test-package")');

    expect(substr_count($html, '<link rel="stylesheet"'))->toBe(1)
        ->and(substr_count($html, '<script'))->toBe(2)
        ->and(strpos($html, '<link'))->toBeLessThan(strpos($html, '<script'));
});

test('naming an entry renders only that one', function () {
    $html = Blade::render('@packageAssets("test-package", "css/index.css")');

    expect($html)->toContain('css/index.css')
        ->and($html)->not->toContain('js/index.js');
});

test('a single url is available for markup the toolkit does not render', function () {
    $url = Blade::render('@packageAssetUrl("test-package", "js/index.js")');

    expect($url)->toStartWith(asset('vendor/test-package/js/index.js').'?id=');
});

test('an undeclared package renders nothing rather than failing', function () {
    expect(Blade::render('@packageAssets("nope")'))->toBe('')
        ->and(app(PackageAssets::class)->declared('nope'))->toBeFalse()
        ->and(app(PackageAssets::class)->url('nope', 'js/index.js'))->toBeNull();
});
