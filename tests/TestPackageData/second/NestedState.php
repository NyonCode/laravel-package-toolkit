<?php

namespace NyonCode\LaravelPackageToolkit\Tests\NestedPackage;

/**
 * State of the nested package, outside its provider's namespace.
 */
class NestedState
{
    /** @var list<string> */
    public static array $log = [];
}
