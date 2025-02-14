<?php

namespace NyonCode\LaravelPackageToolkit\Contracts;

interface HasAbout
{

    /**
     * Returns additional data for AboutCommand.
     *
     * @return array<string>
     */

    public function aboutData(): array;
}