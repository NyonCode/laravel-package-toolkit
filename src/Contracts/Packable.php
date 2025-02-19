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
     * @param Packager $packager The packager instance to configure the package.
     *
     * @return void
     */
    public function configure(Packager $packager): void;
}