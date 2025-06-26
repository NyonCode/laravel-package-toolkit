<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use Illuminate\Support\Facades\Route;
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\Tests\TestPackageData\app\Http\Middleware\TestGlobalMiddleware;

trait PackageMiddlewareGlobalTest
{
    /**
     * Configure the packager instance
     */
    public function configure(Packager $packager): void
    {
        $packager
            ->name('Test Package')
            ->hasMiddlewareGlobals([
                TestGlobalMiddleware::class,
            ]);
    }
}

uses(PackageMiddlewareGlobalTest::class);

beforeEach(function () {
    Route::get('middleware-test', fn () => 'middleware ok');
});

test('global middleware is applied to every request', function () {
    $response = $this->get('middleware-test');
    $response->assertHeader('X-Test-Middleware', 'Global Applied');
});
