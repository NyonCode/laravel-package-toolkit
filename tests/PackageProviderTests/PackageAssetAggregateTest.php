<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\Support\Asset;
use NyonCode\LaravelPackageToolkit\Support\PackageAssets;

/**
 * The no-argument form of the directives, which renders every package that declared
 * entries — the layout line an application does not have to edit when it installs
 * another package of the same family.
 *
 * The second package is declared straight onto the renderer rather than through a
 * second provider: what is under test is how the renderer combines declarations, and
 * the harness boots exactly one test provider.
 */
trait PackageAssetAggregateTest
{
    public function configure(Packager $packager): void
    {
        $packager->name('Test Package')
            ->hasAssets(entries: [
                'js/index.js',
                'css/index.css',
            ]);
    }
}

uses(PackageAssetAggregateTest::class);

// The second package mirrors into `public/` like any other, and the harness only clears
// the one short name it knows about.
afterEach(fn () => File::deleteDirectory(public_path('vendor/other-package')));

/**
 * A second package, sharing the first one's files so nothing new has to be shipped —
 * only the short name it renders under differs.
 *
 * It declares its stylesheet after its script, as the first package does, so an ordering
 * that merely preserved declaration order per package would be visible.
 */
function declareSecondPackage(string $package = 'other-package'): void
{
    app(PackageAssets::class)->declare(
        package: $package,
        directory: __DIR__.'/../TestPackageData/dist',
        entries: [Asset::make('js/legacy.js')->classic(), Asset::make('css/index.css')],
        base: null,
        mirrored: true,
    );
}

test('naming no package renders every package that declared entries', function () {
    declareSecondPackage();

    $html = Blade::render('@packageAssets');

    expect($html)->toContain('vendor/test-package/css/index.css')
        ->and($html)->toContain('vendor/test-package/js/index.js')
        ->and($html)->toContain('vendor/other-package/js/legacy.js');
});

test('stylesheets lead across the whole set, not within each package', function () {
    declareSecondPackage();

    $html = Blade::render('@packageAssets');

    // Both packages declare their script before their stylesheet, so per-package
    // ordering alone would leave the second package's <link> behind the first's
    // <script>. The aggregate renders one document's <head>: every link first.
    expect(substr_count($html, '<link'))->toBe(2)
        ->and(substr_count($html, '<script'))->toBe(2)
        ->and(strrpos($html, '<link'))->toBeLessThan(strpos($html, '<script'));
});

test('the narrowed directives aggregate too', function () {
    declareSecondPackage();

    $styles = Blade::render('@packageStyles');
    $scripts = Blade::render('@packageScripts');

    expect(substr_count($styles, '<link'))->toBe(2)
        ->and($styles)->not->toContain('<script')
        ->and(substr_count($scripts, '<script'))->toBe(2)
        ->and($scripts)->not->toContain('<link');
});

test('each package keeps its own presentation through the aggregate', function () {
    declareSecondPackage();

    $html = Blade::render('@packageScripts');

    // test-package's discovered script is a module; other-package declared classic().
    expect(substr_count($html, 'type="module"'))->toBe(1)
        ->and(substr_count($html, ' defer'))->toBe(1);
});

test('naming a package still renders only that one', function () {
    declareSecondPackage();

    expect(Blade::render('@packageAssets("test-package")'))
        ->not->toContain('other-package');
});

test('the aggregate renders nothing when no package declared entries', function () {
    // Reaches the renderer with an empty registry, which is what an application gets
    // when it puts the directive in a layout before installing anything that declares.
    app()->forgetInstance(PackageAssets::class);
    app()->singleton(PackageAssets::class);

    expect(Blade::render('@packageAssets'))->toBe('')
        ->and(Blade::render('@packageStyles'))->toBe('')
        ->and(Blade::render('@packageScripts'))->toBe('');
});
