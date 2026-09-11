<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use Illuminate\Support\Facades\Blade;
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\Support\PublishedAssets;

/**
 * A booted package with an asset directory and nothing in it to tag — `viteBuild/img`
 * holds one image — which is the ordinary way to arrive at a toolkit provider that
 * declares no entry. A conditional `hasAssets()` not reached in production and an
 * application that wrote the line in its layout before installing anything arrive at the
 * same place.
 *
 * Blade leaves a directive it does not know as text, so the directives are registered
 * whether or not anything declared: without that, a layout carrying `@packageStyles`
 * printed the raw directive into the page, which is worse than rendering nothing and
 * visible in the browser.
 */
trait PackageAssetDirectivesWithoutEntriesTest
{
    public function configure(Packager $packager): void
    {
        $packager->name('Test Package')
            ->hasAssets('viteBuild/img');
    }
}

uses(PackageAssetDirectivesWithoutEntriesTest::class);

test('the directives are registered even though the package declared no entry', function () {
    expect(Blade::getCustomDirectives())
        ->toHaveKeys(['packageAssets', 'packageStyles', 'packageScripts', 'packageAssetUrl']);
});

test('a layout renders nothing rather than printing the directive into the page', function () {
    // `deleteCachedView: true` throughout. `Blade::render()` writes the string to a file
    // named for its own hash and compiles that, and the compiled copy expires only against
    // the string file's mtime — which never changes, since the string does not. Whether a
    // directive was registered is decided at compile time and frozen there, so this test,
    // alone in the suite, would go on asserting whatever the first run compiled.
    $html = Blade::render(
        '<head>@packageStyles("test-package")</head><body>@packageScripts("test-package")</body>',
        deleteCachedView: true,
    );

    expect($html)->toBe('<head></head><body></body>')
        ->and(trim(Blade::render('@packageAssets', deleteCachedView: true)))->toBe('')
        ->and(Blade::render('@packageAssetUrl("test-package", "logo.png")', deleteCachedView: true))
        ->toBe('');
});

test('the package still mirrors what it ships', function () {
    // Nothing to tag is not nothing to serve: the directory is published on demand as
    // ever, and an image is exactly what a template composes a URL for itself.
    $url = app(PublishedAssets::class)->url(
        'test-package',
        __DIR__.'/../TestPackageData/viteBuild/img/logo.png',
    );

    expect($url)->toStartWith(asset('vendor/test-package/logo.png').'?id=')
        ->and(public_path('vendor/test-package/logo.png'))->toBeFile();
});
