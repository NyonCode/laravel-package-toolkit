<?php

namespace NyonCode\LaravelPackageToolkit\Contracts;

use NyonCode\LaravelPackageToolkit\Packager;

interface Packable
{
    /**
     * Configure the package using the given packager instance.
     *
     * This method allows setting up the package's name and other configurations
     * like routes, views, translations, etc., using the provided Packager instance.
     *
     * @param  Packager  $packager  The packager instance to configure the package.
     */
    public function configure(Packager $packager): void;

    /**
     * Actions to perform before registering the package.
     */
    public function registeringPackage(): void;

    /**
     * Actions to perform after registering the package.
     */
    public function registeredPackage(): void;

    /**
     * Actions to perform before booting the package.
     *
     * @return string
     */
    public function bootingPackage(): void;

    /**
     * Actions to perform after booting the package.
     */
    public function bootedPackage(): void;

    /**
     * Get the about data for the package.
     */
    public function aboutData(): array;
}
