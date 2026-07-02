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
     *
     * This method is called immediately before the package is registered.
     */
    public function registeringPackage(): void;

    /**
     * Actions to perform after registering the package.
     *
     * This method is called after the package has been registered and all of its
     * dependencies have been registered.
     */
    public function registeredPackage(): void;

    /**
     * Actions to perform before booting the package.
     *
     * This method is called immediately before the package is booted. You may
     * use this method to perform any setup that is necessary before the
     * package is booted.
     */
    public function bootingPackage(): void;

    /**
     * Actions to perform after booting the package.
     *
     * This method is called after the package has been fully booted and all of its
     * services have been initialized. This is where you can perform any final
     * setup or initialization tasks that need to happen after booting.
     */
    public function bootedPackage(): void;

    /**
     * Get the additional data for the AboutCommand.
     *
     * Override this method to add custom key-value pairs to the package's
     * section in the `php artisan about` command output.
     *
     * @return array<string, string|\Closure> Custom about data.
     */
    public function aboutData(): array;
}
