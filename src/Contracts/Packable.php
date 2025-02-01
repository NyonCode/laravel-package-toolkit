<?php

namespace NyonCode\LaravelPackageToolkit\Contracts;

use NyonCode\LaravelPackageToolkit\Packager;

interface Packable
{
    public function configure(Packager $packager): void;
}