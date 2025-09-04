<?php

namespace NyonCode\LaravelPackageToolkit\Support\Enums;

/**
 * @psalm-immutable
 */
enum LifecycleHook: string
{
    case Booting = 'booting';
    case Booted = 'booted';
    case Registering = 'registering';
    case Registered = 'registered';
}
