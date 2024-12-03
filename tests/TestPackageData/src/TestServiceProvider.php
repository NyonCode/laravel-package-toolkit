<?php

namespace NyonCode\LaravelPackageToolkit\Tests\TestPackageData\src;

use Closure;
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\PackageServiceProvider;

class TestServiceProvider extends PackageServiceProvider
{
    public static ?Closure $providerUsing = null;

    public function configure(Packager $packager): void
    {
        (self::$providerUsing ?? fn(Packager $packager) => null)($packager);
    }
}
