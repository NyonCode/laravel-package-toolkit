<?php

namespace NyonCode\LaravelPackageToolkit\Contracts;

/**
 * @deprecated Will be removed in 3.0. Use the Packable contract instead,
 *             which already declares aboutData().
 */
interface HasAbout
{
    /**
     * Returns additional data for AboutCommand.
     *
     * @return array<string>
     */
    public function aboutData(): array;
}
