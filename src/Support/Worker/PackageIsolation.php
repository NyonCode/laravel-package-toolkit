<?php

declare(strict_types=1);

namespace NyonCode\LaravelPackageToolkit\Support\Worker;

use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use ReflectionClass;
use ReflectionProperty;

/**
 * Make one package behave, request after request, as it would under PHP-FPM — inside a
 * worker that boots once.
 *
 * Under PHP-FPM a package gets a fresh process per request: every instance it made and
 * every static it wrote is gone before the next request starts. A worker keeps both, and
 * a package written for FPM has no reason to have noticed. Two things leak in practice,
 * and this undoes both at the end of every operation:
 *
 * - **Instances the package left on the worker's container.** Octane serves each request
 *   from a clone of the booted application, so an instance resolved *through the clone*
 *   dies with it. One resolved through the original — `$this->app` captured by a
 *   closure, a class listener's dependencies, anything built from a boot-time object
 *   holding the container — stays for every later request. Those are forgotten.
 *   Instances that were already there when the worker finished booting are kept: a
 *   registry filled from `boot()` is the whole point of booting once, and under FPM it
 *   would have been rebuilt identically anyway.
 * - **Static properties of the package's classes.** Put back to the value they held when
 *   the worker finished booting, or to their declared default for a class first loaded
 *   later. A value set from `boot()` therefore survives — a callback registered there,
 *   say — and anything a request wrote does not.
 *
 * The cost is the one FPM always paid: a static cache the package keeps is rebuilt per
 * request instead of per worker. Which is why isolation is opted into per package, for a
 * package the application does not trust under a worker, and not done to every package.
 *
 * What it cannot undo is an object that captured per-request state while the worker
 * booted — the console request a worker boots with, say. That object is kept, because it
 * was there after boot, and nothing short of not doing it fixes it.
 */
final class PackageIsolation
{
    /** @var array<string, true> Abstracts the package's provider bound, whatever their name. */
    private array $owned = [];

    /** @var array<string, true>|null Instance keys the worker's container held after boot. */
    private ?array $kept = null;

    /** @var array<class-string, array<string, mixed>> */
    private array $statics = [];

    /** @var list<array{ReflectionProperty, bool, mixed}> */
    private array $properties = [];

    private int $declaredClasses = 0;

    /** @var list<string> */
    private readonly array $namespaces;

    /** @var array<string, bool> name => whether it is one of the toolkit's own classes */
    private array $toolkit = [];

    private static ?ReflectionProperty $instances = null;

    /**
     * Roots a namespace may not cover. Isolating the framework would forget the router,
     * the view factory and the rest of what a worker boots once to keep.
     */
    private const FRAMEWORK_ROOTS = ['Illuminate\\', 'Laravel\\', 'Symfony\\'];

    /**
     * @param  list<string>  $namespaces  Namespace prefixes, each ending in a backslash
     */
    public function __construct(
        public readonly string $package,
        array $namespaces,
    ) {
        $this->namespaces = array_values(array_filter(
            $namespaces,
            function (string $namespace): bool {
                foreach (self::FRAMEWORK_ROOTS as $root) {
                    if (str_starts_with($namespace, $root) || str_starts_with($root, $namespace)) {
                        return false;
                    }
                }

                return true;
            },
        ));
    }

    /**
     * Record abstracts the package's provider bound.
     *
     * Needed besides the namespaces because a package is free to bind under a name it
     * does not own — a framework contract, a string key — and an instance under that
     * name is still the package's.
     *
     * @param  iterable<string>  $abstracts
     */
    public function own(iterable $abstracts): void
    {
        foreach ($abstracts as $abstract) {
            $this->owned[$abstract] = true;
        }
    }

    public function hasSnapshot(): bool
    {
        return $this->kept !== null;
    }

    /**
     * Remember what the booted worker looks like. Called once, before the first operation.
     */
    public function snapshot(Application $base): void
    {
        $this->kept = array_fill_keys(array_keys(self::instancesOf($base)), true);

        $this->scanClasses();

        foreach ($this->properties as [$property]) {
            $this->statics[$property->class][$property->name] = $property->isInitialized()
                ? $property->getValue()
                : null;
        }
    }

    /**
     * Put the package back the way the booted worker had it.
     *
     * `$instances` and `$declaredClasses` are what the worker looks like right now. Both
     * are the same for every isolated package, so the lifecycle reads them once per
     * operation and hands them to each — measured, reading them per package was most of
     * what isolating a package cost.
     *
     * @param  array<string, mixed>|null  $instances  {@see self::instancesOf()}
     */
    public function restore(Application $base, ?array $instances = null, ?int $declaredClasses = null): void
    {
        if ($this->kept === null) {
            return;
        }

        // Only what appeared since boot can need forgetting — usually nothing, so the
        // ownership checks run on an empty list rather than on every instance.
        foreach (array_diff_key($instances ?? self::instancesOf($base), $this->kept) as $abstract => $instance) {
            if ($this->owns($abstract)) {
                $base->forgetInstance($abstract);
            }
        }

        // A class autoloaded since the last scan is a class whose statics nothing knows
        // about yet. Rescanning only when the count moved keeps the steady state — every
        // class a request touches already loaded — to a single count().
        if (($declaredClasses ?? count(get_declared_classes())) !== $this->declaredClasses) {
            $this->scanClasses();
        }

        foreach ($this->properties as [$property, $hasDefault, $default]) {
            $snapshot = $this->statics[$property->class] ?? [];

            if (array_key_exists($property->name, $snapshot)) {
                $property->setValue(null, $snapshot[$property->name]);
            } elseif ($hasDefault) {
                $property->setValue(null, $default);
            }
        }
    }

    private function owns(string $abstract): bool
    {
        return (isset($this->owned[$abstract]) || $this->inNamespace($abstract))
            && ! $this->isToolkit($abstract);
    }

    private function scanClasses(): void
    {
        $classes = get_declared_classes();
        $this->declaredClasses = count($classes);
        $this->properties = [];

        if ($this->namespaces === []) {
            return;
        }

        foreach ($classes as $class) {
            if (! $this->inNamespace($class) || $this->isToolkit($class)) {
                continue;
            }

            foreach ((new ReflectionClass($class))->getProperties(ReflectionProperty::IS_STATIC) as $property) {
                // Declared here, not inherited: an inherited static is the parent's one
                // slot, and the parent is restored when its own class comes up.
                if ($property->class !== $class) {
                    continue;
                }

                $this->properties[] = [$property, $property->hasDefaultValue(), $property->getDefaultValue()];
            }
        }
    }

    private function inNamespace(string $class): bool
    {
        foreach ($this->namespaces as $namespace) {
            if (str_starts_with($class, $namespace)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether a name is one of the toolkit's own classes — told by where its file is, not
     * by its namespace, because `NyonCode\` is as natural a namespace to isolate as any
     * and the toolkit's state (the asset mirror, this lifecycle) is not a package's to
     * reset. A name that is not a class — a string key — is not the toolkit's.
     */
    private function isToolkit(string $name): bool
    {
        if (isset($this->toolkit[$name])) {
            return $this->toolkit[$name];
        }

        $file = class_exists($name, false) || interface_exists($name, false) || trait_exists($name, false)
            ? (new ReflectionClass($name))->getFileName()
            : false;

        return $this->toolkit[$name] = $file !== false && str_starts_with($file, dirname(__DIR__, 2).DIRECTORY_SEPARATOR);
    }

    /**
     * The container's resolved shared instances. The container has no public reader for
     * them, and asking `resolved()` per binding would miss a `#[Singleton]` class that was
     * never bound at all.
     *
     * @internal Read by {@see WorkerLifecycle} once per operation for every isolation.
     *
     * @return array<string, mixed>
     */
    public static function instancesOf(Application $app): array
    {
        self::$instances ??= new ReflectionProperty(Container::class, 'instances');

        /** @var array<string, mixed> */
        return self::$instances->getValue($app);
    }
}
