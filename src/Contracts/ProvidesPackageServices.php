<?php

namespace NyonCode\LaravelPackageToolkit\Contracts;

interface ProvidesPackageServices
{
    /**
     * Actions to perform before registering the package.
     *
     * This method is called immediately before the package is registered.
     *
     * @return void
     */
    public function registeringPackage(): void;

    /**
     * Register the package.
     *
     * This method is called after the package has been booted and all of its
     * dependencies have been registered. This method is where you should
     * register any of your package's services, commands, route files, etc.
     *
     * @return void
     */
    public function register(): void;

    /**
     * Actions to perform after registering the package.
     *
     * This method is called after the package has been registered and all of its
     * dependencies have been registered. This method is where you should
     * register any of your package's services, commands, route files, etc.
     *
     * @return void
     */
    public function registeredPackage(): void;

    /**
     * Actions to perform before booting the package.
     *
     * This method is called immediately before the package is booted. You may
     * use this method to perform any setup that is necessary before the
     * package is booted.
     *
     * @return void
     */
    public function bootingPackage(): void;

    /**
     * Boot the package.
     *
     * This method is called after the package has been registered and all of its
     * dependencies have been registered. This method is where you should
     * register any of your package's services, commands, route files, etc.
     *
     * @return void
     */
    public function boot(): void;

    /**
     * Actions to perform after booting the package.
     *
     * This method is called after the package has been fully booted and all of its
     * services have been initialized. This is where you can perform any final
     * setup or initialization tasks that need to happen after booting.
     *
     * @return void
     */
    public function bootedPackage(): void;
}