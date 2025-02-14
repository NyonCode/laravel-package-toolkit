<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use Exception;
use NyonCode\LaravelPackageToolkit\Packager;

trait PackageRoutesTest
{

    public function configure(Packager $packager): void
    {
        $packager->name('Test Package Route')->hasRoutes();
    }
}

uses(PackageRoutesTest::class);

test('can register a  router', function () {
    expect($this->get('first-route')->getStatusCode())
        ->toBe(200)
        ->and($this->get('foo')->getStatusCode())
        ->toBe(200);
});

test('route say hello', function () {
    $response = $this->get('first-route');
    $response->assertSee('response route');
});

test('route foo say bar', function () {
    $response = $this->get('foo');
    $response->assertSee('bar');
});
