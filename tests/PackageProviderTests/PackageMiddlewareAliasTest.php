<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use Illuminate\Support\Facades\Route;
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\Tests\TestPackageData\app\Http\Middleware\TestAliasMiddleware;

trait PackageMiddlewareAliasTest
{
    /**
     * Configure the packager instance
     */
    public function configure(Packager $packager): void
    {
        $packager
            ->name('Test Package')
            ->hasMiddlewareAliases([
                'test.alias' => TestAliasMiddleware::class,
            ]);
    }
}

uses(PackageMiddlewareAliasTest::class);

beforeEach(function () {
    Route::get('middleware-test', fn () => 'middleware ok')->middleware('test.alias');
});

test('middleware alias is applied', function () {
    $response = $this->get('middleware-test');
    $response->assertHeader('X-Test-Middleware', 'Alias Applied');
});