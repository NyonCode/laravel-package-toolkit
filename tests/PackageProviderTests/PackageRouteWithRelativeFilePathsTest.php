<?php

namespace NyonCode\LaravelPackageBuilder\Tests\PackageProviderTests;

use Exception;
use NyonCode\LaravelPackageBuilder\Packager;

trait PackageRouteWithRelativeFilePathsTest
{
    /**
     * @throws Exception
     */
    public function configure(Packager $packager): void
    {
        $packager->name('Package Test')->hasRoutes([
            'foo.php',
            '../routes/test.php',
            '../alternativeRoutes/www/web.php'
        ]);
    }
}

uses(PackageRouteWithRelativeFilePathsTest::class);

test('routes from foo.php are properly registered and accessible', function () {
    $responseFoo = $this->get('foo');
    $responseBar = $this->get('bar');

    $responseFoo->assertStatus(200);
    $responseFoo->assertSee('bar');

    // Bar not defined 404 success status
    $responseBar->assertStatus(404);
});

test('routes from test.php are properly registered and accessible', function () {
    $responseTest = $this->get('first-route');

    $responseTest->assertStatus(200);
    $responseTest->assertSee('response route');
});

test('routes for web.php in alternative path are properly registered and accessible', function () {
    $responseWeb = $this->get('alternative');

    $responseWeb->assertStatus(200);
    $responseWeb->assertSee('Hello World!');
});