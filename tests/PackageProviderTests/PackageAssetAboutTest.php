<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use Illuminate\Support\Facades\Blade;
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\PackageServiceProvider;
use NyonCode\LaravelPackageToolkit\Tests\TestPackageData\src\TestServiceProvider;
use ReflectionClass;

/**
 * The about command reports how each entry resolves, but only for a package whose
 * entries the application could have built — a shipped-only package has nothing to
 * report that the publish tags do not already say.
 */
trait PackageAssetAboutTest
{
    public function configure(Packager $packager): void
    {
        $packager->name('Test Package')
            ->hasAbout()
            ->hasAssets(entries: ['css/index.css']);
    }
}

uses(PackageAssetAboutTest::class);

test('a shipped-only package reports no asset resolution', function () {
    $this->artisan('about --only=test_package')
        ->doesntExpectOutputToContain('css/index.css')
        ->assertSuccessful();
});

test('registering the directives twice leaves one registration each', function () {
    /** @var PackageServiceProvider $provider */
    $provider = $this->app->getProvider(TestServiceProvider::class);

    $before = array_keys(Blade::getCustomDirectives());

    $provider->bootAssets();

    expect(array_keys(Blade::getCustomDirectives()))->toBe($before);
});

test('the vite base is derived from the package position under the application', function () {
    /** @var PackageServiceProvider $provider */
    $provider = $this->app->getProvider(TestServiceProvider::class);

    $derive = (new ReflectionClass($provider))->getMethod('derivePackageBase');

    // The package fixture lives outside the test application's root.
    expect($derive->invoke($provider))->toBeNull();

    $this->app->setBasePath(dirname(__DIR__));
    expect($derive->invoke($provider))->toBe('TestPackageData');

    $this->app->setBasePath(dirname(__DIR__).'/TestPackageData');
    expect($derive->invoke($provider))->toBe('');
});
