<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use NyonCode\LaravelPackageToolkit\Packager;
use function PHPUnit\Framework\assertTrue;

trait PackageNameTest
{

    public function configure(Packager $packager): void
    {
        $packager->name('Test Package');
    }
}

uses(PackageNameTest::class);

test('is not displayed when setting the name', fn() => assertTrue(true));