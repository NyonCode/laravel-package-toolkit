<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use Exception;
use NyonCode\LaravelPackageToolkit\Packager;

trait PackageRouteWithFileNameTest
{

    public function configure(Packager $packager): void
    {
        $packager->name('Test Package')->hasRoutes('foo.php');
    }
}

uses(PackageRouteWithFileNameTest::class);

test('route with filename test', function () {
    $response = $this->get('foo');
    $response->assertStatus(200);
});


test('undefined route not accessible', function () {
    $response = $this->get('first-route');
    $response->assertStatus(404);
});


