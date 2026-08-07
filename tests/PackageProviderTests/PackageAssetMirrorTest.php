<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use Illuminate\Support\Facades\File;
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\Support\PublishedAssets;

trait PackageAssetMirrorTest
{
    public function configure(Packager $packager): void
    {
        $packager->name('Test Package')
            ->hasAssets();
    }
}

uses(PackageAssetMirrorTest::class);

test('the mirror is registered as a shared singleton', function () {
    expect(app()->bound(PublishedAssets::class))->toBeTrue()
        ->and(app(PublishedAssets::class))->toBe(app(PublishedAssets::class));
});

test('resolving one asset mirrors the whole directory into public', function () {
    $shipped = __DIR__.'/../TestPackageData/dist/css/index.css';

    $url = app(PublishedAssets::class)->url('test-package', $shipped);

    expect(public_path('vendor/test-package/css/index.css'))->toBeFile()
        // Every shipped file is copied, not just the one that was resolved —
        // a code-split chunk is fetched by the browser, never by PHP.
        ->and(public_path('vendor/test-package/js/index.js'))->toBeFile()
        ->and($url)->toBe(
            asset('vendor/test-package/css/index.css')
            .'?id='.filemtime(public_path('vendor/test-package/css/index.css'))
        );
});

test('a published copy newer than the shipped one is left alone', function () {
    $shipped = __DIR__.'/../TestPackageData/dist/css/index.css';
    $published = public_path('vendor/test-package/css/index.css');

    File::ensureDirectoryExists(dirname($published));
    File::put($published, '/* published by hand */');
    touch($published, filemtime($shipped) + 10);

    app(PublishedAssets::class)->url('test-package', $shipped);

    expect(File::get($published))->toBe('/* published by hand */')
        ->and(app(PublishedAssets::class)->isStale('test-package', $shipped))->toBeFalse();
});

test('a published copy older than the shipped one is replaced', function () {
    $shipped = __DIR__.'/../TestPackageData/dist/css/index.css';
    $published = public_path('vendor/test-package/css/index.css');

    File::ensureDirectoryExists(dirname($published));
    File::put($published, '/* stale */');
    touch($published, filemtime($shipped) - 10);

    app(PublishedAssets::class)->url('test-package', $shipped);

    expect(File::get($published))->toBe(File::get($shipped))
        ->and(app(PublishedAssets::class)->isStale('test-package', $shipped))->toBeFalse();
});

test('assets publish under the package tag', function () {
    $this->artisan('vendor:publish --tag=test-package::assets')->assertExitCode(0);

    expect(public_path('vendor/test-package/css/index.css'))->toBeFile()
        ->and(public_path('vendor/test-package/js/index.js'))->toBeFile();
});

test('assets publish under the conventional laravel-assets tag', function () {
    $this->artisan('vendor:publish --tag=laravel-assets')->assertExitCode(0);

    expect(public_path('vendor/test-package/css/index.css'))->toBeFile()
        ->and(public_path('vendor/test-package/js/index.js'))->toBeFile();
});
