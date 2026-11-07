<?php

namespace NyonCode\LaravelPackageToolkit\Tests\SecondPackage;

use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\PackageServiceProvider;

/**
 * A second toolkit package in its own namespace, for what only shows with two: one
 * lifecycle shared by both, and one package's isolation leaving the other alone.
 */
class SecondServiceProvider extends PackageServiceProvider
{
    public function configure(Packager $packager): void
    {
        $packager->name('Second Package')
            ->registeredPackage(fn () => app()->singleton(SecondService::class));
    }
}

class SecondService {}

class SecondState
{
    /** @var list<string> */
    public static array $log = [];
}
