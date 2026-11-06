<?php

namespace NyonCode\LaravelPackageToolkit\Tests\TestPackageData\src\Late;

/**
 * Autoloaded on first use — never by a boot — so isolation meets it only after its snapshot.
 *
 * Kept outside the package directory, through a classmap: anything inside it is loaded by
 * `package-toolkit:octane` scanning the package, and would no longer be loaded late.
 */
class LateLoaded
{
    public static int $hits = 0;
}
