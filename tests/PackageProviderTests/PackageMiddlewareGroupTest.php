<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use Illuminate\Support\Facades\Route;
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\Tests\TestPackageData\app\Http\Middleware\TestGroupMiddleware;

trait PackageMiddlewareGroupTest
{
    /**
     * Configure the packager instance
     */
    public function configure(Packager $packager): void
    {
        $packager
            ->name('Test Package')
            ->hasMiddlewareGroups([
                'web' => [
                    TestGroupMiddleware::class,
                ],
            ]);
    }
}

uses(PackageMiddlewareGroupTest::class);

beforeEach(function () {
    Route::middleware('web')->get('middleware-group-test', fn () => 'group ok');
});

test('middleware group is applied to web group route', function () {

    $response = $this->get('middleware-group-test');
    $response->assertHeader('X-Test-Middleware', 'Group Applied');
});
