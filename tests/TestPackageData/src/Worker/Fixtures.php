<?php

namespace NyonCode\LaravelPackageToolkit\Tests\TestPackageData\src\Worker;

use Illuminate\Http\Request;

/** Resolved lazily, inside a request. */
class LazyService {}

/** Resolved while the package boots, and filled from there — a registry. */
class BootRegistry
{
    /** @var list<string> */
    public array $entries = [];
}

/** Bound under a name the package does not own. */
class ForeignNamed {}

/** A dependency of a class listener. */
class ListenerDependency {}

class Pinged {}

class PingListener
{
    public function __construct(public ListenerDependency $dependency) {}

    public function handle(Pinged $event): void {}
}

/** Static state a request writes. */
class Stateful
{
    /** @var list<string> */
    public static array $log = [];

    public static string $mode = 'declared';
}

/** Built while the package boots, holding the request it booted with. */
class HoldsRequest
{
    public function __construct(public Request $request) {}
}

/** A listener bound as a singleton: meant to be one instance for the worker. */
class SharedPingListener
{
    public function handle(Pinged $event): void {}
}
