<?php

declare(strict_types=1);

namespace NyonCode\LaravelPackageToolkit\Commands;

use Illuminate\Console\Command;
use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Application;
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\PackageServiceProvider;
use NyonCode\LaravelPackageToolkit\Support\Worker\WorkerLifecycle;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use SplFileInfo;
use Throwable;

/**
 * Report what a long-lived worker would keep from one request to the next, for every
 * package built on the toolkit — the part the toolkit cannot fix on its own.
 *
 * Run from the console, so the application it inspects booted the way a worker boots
 * (plus whatever a provider does only in the console — the report errs on the side of
 * listing too much). Three things are looked for:
 *
 * - **Objects resolved at boot that hold per-request state.** Kept for the worker's whole
 *   life, and built while the worker booted — against the console request a worker boots
 *   with, the worker's container, the worker's configuration. Isolation keeps them,
 *   because they were there after boot; only the package can stop holding them.
 * - **Class listeners with shared dependencies.** Built through the event dispatcher's
 *   container, which is the worker's, unless `package-toolkit.octane.sandbox_events` is on.
 * - **Static properties.** Survive every request. Arrays and objects are listed — a cache
 *   that only grows, a captured instance — and scalars too with `--all`.
 *
 * Nothing is changed. `--strict` turns the first two into a failing exit code for CI.
 */
class OctaneDiagnoseCommand extends Command
{
    /**
     * Types whose instance belongs to one request, or that hand out what does.
     */
    private const PER_REQUEST_TYPES = [
        'Illuminate\Http\Request',
        'Symfony\Component\HttpFoundation\Request',
        'Illuminate\Contracts\Foundation\Application',
        'Illuminate\Foundation\Application',
        'Illuminate\Contracts\Container\Container',
        'Illuminate\Container\Container',
        'Illuminate\Contracts\Config\Repository',
        'Illuminate\Config\Repository',
        'Illuminate\Contracts\Session\Session',
        'Illuminate\Session\Store',
        'Illuminate\Contracts\Auth\Guard',
        'Illuminate\Contracts\Auth\StatefulGuard',
        'Illuminate\Contracts\Auth\Authenticatable',
        'Illuminate\Routing\Route',
        'Illuminate\Contracts\Routing\UrlGenerator',
        'Illuminate\Routing\UrlGenerator',
    ];

    protected $signature = 'package-toolkit:octane
                            {--all : Also list scalar static properties}
                            {--strict : Fail when an object held from boot or a listener needs attention}';

    protected $description = 'Report what toolkit packages keep from one request to the next under a long-lived worker';

    private int $risks = 0;

    public function handle(): int
    {
        $app = $this->laravel;

        if (! $app instanceof Application) {
            $this->components->error('The application is not a Laravel application instance.');

            return self::FAILURE;
        }

        $this->reportConfiguration($app);

        foreach ($app->getProviders(PackageServiceProvider::class) as $provider) {
            $packager = $this->packagerOf($provider);

            if ($packager === null) {
                continue;
            }

            $this->reportPackage($app, $provider, $packager);
        }

        if ($this->risks === 0) {
            $this->components->info('Nothing held from boot or built on the worker container needs attention.');

            return self::SUCCESS;
        }

        $this->components->warn("$this->risks finding(s) need attention under a long-lived worker.");

        return $this->option('strict') ? self::FAILURE : self::SUCCESS;
    }

    private function reportConfiguration(Application $app): void
    {
        $config = $app->make('config');
        $isolate = array_map(
            fn (mixed $key, mixed $value): string => is_int($key) ? (string) $value : $key,
            array_keys((array) $config->get('package-toolkit.octane.isolate', [])),
            (array) $config->get('package-toolkit.octane.isolate', []),
        );

        $this->newLine();
        $this->components->twoColumnDetail('<fg=green;options=bold>Worker configuration</>');
        $this->components->twoColumnDetail('Providers follow the operation', $this->onOff((bool) $config->get('package-toolkit.octane.sandbox_providers', true)));
        $this->components->twoColumnDetail('Event dispatcher follows the operation', $this->onOff($this->sandboxesEvents($app)));
        $this->components->twoColumnDetail('Isolated packages', $isolate === [] ? '<fg=gray>none</>' : implode(', ', $isolate));
    }

    private function reportPackage(Application $app, PackageServiceProvider $provider, Packager $packager): void
    {
        $namespace = WorkerLifecycle::packageNamespace($provider);

        if ($namespace === '') {
            return;
        }

        $namespace .= '\\';
        $classes = $this->packageClasses($packager, $namespace);

        $held = $this->heldFromBoot($app, $namespace);
        $listeners = $this->sandboxesEvents($app) ? [] : $this->listenersWithSharedDependencies($app, $namespace);
        $statics = $this->staticProperties($classes);

        $this->newLine();
        $this->components->twoColumnDetail(
            '<fg=green;options=bold>'.$packager->shortName().'</>',
            '<fg=gray>'.rtrim($namespace, '\\').'</>',
        );

        if ($held === [] && $listeners === [] && $statics === []) {
            $this->components->twoColumnDetail('Nothing found', '<fg=green>ok</>');

            return;
        }

        // Named relative to the package's namespace: the section heading already says
        // which namespace it is, and a full name pushes the detail off a narrow terminal.
        $relative = fn (string $name): string => str_starts_with($name, $namespace)
            ? substr($name, strlen($namespace))
            : $name;

        foreach ($held as $class => $parameters) {
            $this->risks++;
            $this->components->twoColumnDetail(
                '<fg=red>held from boot</> '.$relative($class),
                'takes '.implode(', ', $parameters),
            );
        }

        foreach ($listeners as $listener => $dependencies) {
            $this->risks++;
            $this->components->twoColumnDetail(
                '<fg=yellow>class listener</> '.$relative($listener),
                'shared '.implode(', ', array_map($relative, $dependencies)),
            );
        }

        foreach ($statics as $property => $type) {
            $this->components->twoColumnDetail('<fg=gray>static</> '.$relative($property), $type);
        }
    }

    /**
     * Instances the container already holds, of a class in the package, whose
     * constructor took something that belongs to one request.
     *
     * @return array<class-string, list<string>>
     */
    private function heldFromBoot(Application $app, string $namespace): array
    {
        $held = [];

        foreach ($this->instancesOf($app) as $instance) {
            if (! is_object($instance) || ! str_starts_with($instance::class, $namespace)) {
                continue;
            }

            $parameters = $this->perRequestParameters($instance::class);

            if ($parameters !== []) {
                $held[$instance::class] = $parameters;
            }
        }

        return $held;
    }

    /**
     * Class listeners of the package whose constructor takes a shared binding.
     *
     * @return array<string, list<string>>
     */
    private function listenersWithSharedDependencies(Application $app, string $namespace): array
    {
        $events = $app->make('events');

        if (! $events instanceof Dispatcher) {
            return [];
        }

        $found = [];

        foreach ($events->getRawListeners() as $listeners) {
            foreach ((array) $listeners as $listener) {
                $class = match (true) {
                    is_string($listener) => explode('@', $listener)[0],
                    is_array($listener) && is_string($listener[0] ?? null) => $listener[0],
                    default => null,
                };

                if ($class === null || ! str_starts_with($class, $namespace) || ! class_exists($class)) {
                    continue;
                }

                $shared = [];

                foreach ($this->constructorTypes($class) as $type) {
                    if ($app->bound($type) && $app->isShared($type)) {
                        $shared[] = $type;
                    }
                }

                if ($shared !== []) {
                    $found[$class] = $shared;
                }
            }
        }

        return $found;
    }

    /**
     * @param  list<class-string>  $classes
     * @return array<string, string> `Class::$property` => type
     */
    private function staticProperties(array $classes): array
    {
        $found = [];

        foreach ($classes as $class) {
            foreach ((new ReflectionClass($class))->getProperties(ReflectionProperty::IS_STATIC) as $property) {
                if ($property->class !== $class) {
                    continue;
                }

                $type = $property->getType();
                $name = $type instanceof ReflectionNamedType ? $type->getName() : (string) ($type ?? 'mixed');
                $scalar = $type instanceof ReflectionNamedType && $type->isBuiltin()
                    && in_array($name, ['string', 'int', 'float', 'bool'], true);

                if ($scalar && ! $this->option('all')) {
                    continue;
                }

                $found["$class::\${$property->getName()}"] = ($type?->allowsNull() && $name !== 'mixed' && $name !== 'null' ? '?' : '').$name;
            }
        }

        return $found;
    }

    /**
     * Classes declared in the package's files under its namespace, loaded through the
     * autoloader. A file that fails to load is skipped, not reported — that is a problem
     * of its own, and not one a worker introduces.
     *
     * @return list<class-string>
     */
    private function packageClasses(Packager $packager, string $namespace): array
    {
        $directory = $packager->basePath();

        if (! is_dir($directory)) {
            return [];
        }

        $classes = [];
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            foreach ($this->classesDeclaredIn($file->getPathname()) as $class) {
                if (! str_starts_with($class, $namespace)) {
                    continue;
                }

                try {
                    if (class_exists($class) && ! (new ReflectionClass($class))->isEnum()) {
                        $classes[] = $class;
                    }
                } catch (Throwable) {
                    continue;
                }
            }
        }

        sort($classes);

        return $classes;
    }

    /**
     * @return list<string>
     */
    private function classesDeclaredIn(string $path): array
    {
        $source = @file_get_contents($path);

        if ($source === false || ! str_contains($source, 'class')) {
            return [];
        }

        $tokens = token_get_all($source);
        $namespace = '';
        $classes = [];

        foreach ($tokens as $index => $token) {
            if (! is_array($token)) {
                continue;
            }

            if ($token[0] === T_NAMESPACE) {
                $namespace = '';

                for ($i = $index + 1; isset($tokens[$i]) && $tokens[$i] !== ';' && $tokens[$i] !== '{'; $i++) {
                    if (is_array($tokens[$i]) && in_array($tokens[$i][0], [T_NAME_QUALIFIED, T_STRING], true)) {
                        $namespace .= $tokens[$i][1];
                    }
                }
            }

            if ($token[0] === T_CLASS) {
                // `Foo::class` and `new class` are not declarations.
                $previous = $this->previousMeaningful($tokens, $index);

                if ($previous === T_DOUBLE_COLON || $previous === T_NEW) {
                    continue;
                }

                for ($i = $index + 1; isset($tokens[$i]); $i++) {
                    if (is_array($tokens[$i]) && $tokens[$i][0] === T_STRING) {
                        $classes[] = ltrim($namespace.'\\'.$tokens[$i][1], '\\');

                        break;
                    }

                    if (! is_array($tokens[$i]) || $tokens[$i][0] !== T_WHITESPACE) {
                        break;
                    }
                }
            }
        }

        return $classes;
    }

    /**
     * @param  array<int, array{0: int, 1: string, 2: int}|string>  $tokens
     */
    private function previousMeaningful(array $tokens, int $index): ?int
    {
        for ($i = $index - 1; $i >= 0; $i--) {
            $token = $tokens[$i];

            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            return is_array($token) ? $token[0] : null;
        }

        return null;
    }

    /**
     * @return list<string> `Type $name` for each per-request constructor parameter
     */
    private function perRequestParameters(string $class): array
    {
        if (! class_exists($class)) {
            return [];
        }

        $constructor = (new ReflectionClass($class))->getConstructor();
        $found = [];

        foreach ($constructor?->getParameters() ?? [] as $parameter) {
            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && in_array($type->getName(), self::PER_REQUEST_TYPES, true)) {
                $found[] = class_basename($type->getName()).' $'.$parameter->getName();
            }
        }

        return $found;
    }

    /**
     * @param  class-string  $class
     * @return list<string>
     */
    private function constructorTypes(string $class): array
    {
        $constructor = (new ReflectionClass($class))->getConstructor();
        $types = [];

        foreach ($constructor?->getParameters() ?? [] as $parameter) {
            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && ! $type->isBuiltin()) {
                $types[] = $type->getName();
            }
        }

        return $types;
    }

    private function packagerOf(PackageServiceProvider $provider): ?Packager
    {
        $packager = (new ReflectionProperty(PackageServiceProvider::class, 'packager'))->getValue($provider);

        return $packager instanceof Packager ? $packager : null;
    }

    private function sandboxesEvents(Application $app): bool
    {
        return $app->bound(WorkerLifecycle::class)
            ? $app->make(WorkerLifecycle::class)->sandboxesEvents()
            : (bool) $app->make('config')->get('package-toolkit.octane.sandbox_events', false);
    }

    /**
     * @return array<string, mixed>
     */
    private function instancesOf(Application $app): array
    {
        /** @var array<string, mixed> */
        return (new ReflectionProperty(Container::class, 'instances'))->getValue($app);
    }

    private function onOff(bool $on): string
    {
        return $on ? '<fg=green>on</>' : '<fg=gray>off</>';
    }
}
