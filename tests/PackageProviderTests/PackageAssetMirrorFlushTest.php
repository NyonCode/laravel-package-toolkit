<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\Support\PublishedAssets;

trait PackageAssetMirrorFlushTest
{
    public function configure(Packager $packager): void
    {
        // Deliberately not `dist`: with `dist` the resolver could read the asset
        // directory back off the asset path, and a `flush()` that wrongly cleared the
        // declared directories would still resolve the same URL — the test below would
        // pass against a broken implementation.
        $packager->name('Test Package')
            ->hasAssets('assets');
    }
}

uses(PackageAssetMirrorFlushTest::class);

test('flush forgets a resolved url', function () {
    $shipped = __DIR__.'/../TestPackageData/assets/js/index.js';
    $published = public_path('vendor/test-package/js/index.js');

    $before = app(PublishedAssets::class)->url('test-package', $shipped);

    touch($published, filemtime($published) + 10);
    clearstatcache(true, $published);

    expect(app(PublishedAssets::class)->url('test-package', $shipped))->toBe($before);

    app(PublishedAssets::class)->flush();

    expect(app(PublishedAssets::class)->url('test-package', $shipped))
        ->toBe(asset('vendor/test-package/js/index.js').'?id='.filemtime($published))
        ->not->toBe($before);
});

test('flush lets the mirror run again', function () {
    $shipped = __DIR__.'/../TestPackageData/assets/js/index.js';
    $published = public_path('vendor/test-package/js/index.js');

    app(PublishedAssets::class)->url('test-package', $shipped);

    unlink($published);
    clearstatcache(true, $published);

    app(PublishedAssets::class)->flush();

    expect(app(PublishedAssets::class)->url('test-package', $shipped))->not->toBeNull()
        ->and($published)->toBeFile();
});

test('flush keeps the declared asset directories', function () {
    $shipped = __DIR__.'/../TestPackageData/assets/js/index.js';

    app(PublishedAssets::class)->url('test-package', $shipped);
    app(PublishedAssets::class)->flush();

    // Still the path *inside* the declared directory — neither `null` (no directory to
    // walk) nor a flattened `vendor/test-package/index.js`.
    expect(app(PublishedAssets::class)->url('test-package', $shipped))
        ->toStartWith(asset('vendor/test-package/js/index.js').'?id=');
});

test('flush on an untouched resolver publishes nothing', function () {
    $shipped = __DIR__.'/../TestPackageData/assets/js/index.js';

    app(PublishedAssets::class)->flush();

    expect(public_path('vendor/test-package'))->not->toBeDirectory()
        ->and(app(PublishedAssets::class)->url('test-package', $shipped))
        ->toStartWith(asset('vendor/test-package/js/index.js').'?id=');
});
