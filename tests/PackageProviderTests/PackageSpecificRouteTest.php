<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use Exception;
use NyonCode\LaravelPackageToolkit\Packager;

trait PackageSpecificRouteTest
{
    /**
     * @throws Exception
     */
    public function configure(Packager $packager): void
    {
        $packager->name('Test Package')->hasRoutes('../routes/test.php');
    }
}

uses(PackageSpecificRouteTest::class);

test('can register a router', function () {
    expect($this->get('first-route')->getStatusCode())->toBe(200);
});

test('route say hello', function () {
    $response = $this->get('first-route');
    $response->assertSee('response route');
});

test('unregistered route says 404', function () {
    expect($this->get('foo')->getStatusCode())->toBe(404);
});