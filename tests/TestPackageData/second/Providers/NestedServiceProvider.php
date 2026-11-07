<?php

namespace NyonCode\LaravelPackageToolkit\Tests\NestedPackage\Providers;

use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\PackageServiceProvider;

/**
 * A package whose provider sits in a `Providers` namespace, one level below the classes
 * it ships — the layout of a package with `src/Providers/`.
 */
class NestedServiceProvider extends PackageServiceProvider
{
    public function configure(Packager $packager): void
    {
        $packager->name('Nested Package');
    }
}
