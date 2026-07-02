<?php

namespace NyonCode\LaravelPackageToolkit\Contracts;

interface ProvidesPackageServices extends Packable
{
    /**
     * Register the package.
     *
     * This method is called after the package has been booted and all of its
     * dependencies have been registered. This method is where you should
     * register any of your package's services, commands, route files, etc.
     */
    public function register(): void;

    /**
     * Boot the package.
     *
     * This method is called after the package has been registered and all of its
     * dependencies have been registered. This method is where you should
     * register any of your package's services, commands, route files, etc.
     */
    public function boot(): void;

    /**
     * Get the list of package commands.
     *
     * Override this method to return an array of console commands
     * specific to the package.
     *
     * @return array<string|object> List of command classes.
     */
    public function packageCommands(): array;
}
