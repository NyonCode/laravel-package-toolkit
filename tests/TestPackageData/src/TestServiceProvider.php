<?php

namespace NyonCode\LaravelPackageBuilder\Tests\TestPackageData\src;

use Closure;
use NyonCode\LaravelPackageBuilder\Packager;
use NyonCode\LaravelPackageBuilder\PackageServiceProvider;

class TestServiceProvider extends PackageServiceProvider
{
    public static ?Closure $providerUsing = null;

    public function configure(Packager $packager): void
    {
        (self::$providerUsing ?? fn(Packager $packager) => null)($packager);
    }
}
