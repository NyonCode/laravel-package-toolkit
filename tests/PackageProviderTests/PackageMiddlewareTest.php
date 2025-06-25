<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use Illuminate\Support\Facades\Route;
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\Tests\TestPackageData\app\Http\Middleware\TestAliasMiddleware;
use NyonCode\LaravelPackageToolkit\Tests\TestPackageData\app\Http\Middleware\TestGlobalMiddleware;
use NyonCode\LaravelPackageToolkit\Tests\TestPackageData\app\Http\Middleware\TestGroupMiddleware;

trait PackageMiddlewareTest
{
    /**
     * Configure the packager instance
     *
     * @param Packager $packager
     * @return void
     */
    public function configure(Packager $packager): void
    {
        $packager
            ->name('Test Package')
            ->hasMiddlewareAliases([
                'test.alias' => TestAliasMiddleware::class
            ])
            ->hasMiddlewareGroups([
                'web' => [
                    TestGroupMiddleware::class
                ]
            ])
            ->hasMiddlewareGlobals([
                TestGlobalMiddleware::class
            ]);
    }
}

uses(PackageMiddlewareTest::class);

beforeEach(function () {
    Route::get('middleware-test', fn () => 'middleware ok')->middleware('test.alias');
});

test('middleware alias is applied', function () {
    $response = $this->get('middleware-test');
    $response->assertSee('Middleware alias see');
});

test('middleware group is applied to web group route', function () {
    Route::middleware('web')->get('middleware-group-test', fn () => 'group ok');

    $response = $this->get('middleware-group-test');
    $response->assertSee('Middleware group see');
});

test('global middleware is applied to every request', function () {
    $response = $this->get('middleware-test');
    $response->assertSee('Global middleware see');
});