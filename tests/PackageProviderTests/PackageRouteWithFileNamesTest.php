<?php

namespace NyonCode\LaravelPackageBuilder\Tests\PackageProviderTests;

use Exception;
use NyonCode\LaravelPackageBuilder\Packager;

trait PackageRouteWithFileNamesTest
{
    /**
     * @throws Exception
     */
    public function configure(Packager $packager): void
    {
        $packager->name('Test Package')->hasRoutes(['foo.php', 'test.php']);
    }
}

uses(PackageRouteWithFileNamesTest::class);

test('can accessible on route', function () {
    $responseFoo = $this->get('foo');
    $responseBar = $this->get('bar');
    $responseTest = $this->get('first-route');

    $responseFoo->assertStatus(200);
    $responseBar->assertStatus(404);
    $responseTest->assertStatus(200);
});