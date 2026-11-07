<?php

declare(strict_types=1);

namespace NyonCode\LaravelPackageToolkit\Support\Worker;

use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use NyonCode\LaravelPackageToolkit\PackageServiceProvider;
use NyonCode\LaravelPackageToolkit\Support\PublishedAssets;
use NyonCode\LaravelPackageToolkit\Support\Runtime;
use ReflectionClass;
use ReflectionProperty;

/**
 * What the toolkit does between two operations of a long-lived worker, so that a package
 * written for PHP-FPM can be served by one without being changed.
 *
 * Octane serves every request (and every task and tick) from a clone of the booted
 * application, and points `app()`, the facades and a list of framework services at the
 * clone. Two things it does not point there, and both are where packages leak state from
 * one request into the next:
 *
 * - **A provider's own `$this->app`.** It stays the booted original, so a closure a
 *   provider registered — a route, a view composer, a lifecycle hook, an event listener —
 *   that calls `$this->app->make()` while a request runs resolves on the original. A
 *   singleton made that way outlives the request, and `$this->app['config']` reads the
 *   worker's configuration instead of the request's copy. Every toolkit provider is
 *   pointed at the clone for the duration of the operation, and back afterwards.
 * - **The event dispatcher's container.** `Event::listen(Ping::class, Listener::class)`
 *   builds the listener through the dispatcher's container, which is the original too, so
 *   the listener's dependencies outlive the request. Pointing the dispatcher at the clone
 *   fixes that for the whole application — not only for packages — which is why it is
 *   opt-in (`package-toolkit.octane.sandbox_events`).
 *
 * After every operation it also forgets the toolkit's own per-request memo (the asset
 * mirror's resolved URLs) and restores every package the application asked to isolate
 * ({@see PackageIsolation}).
 *
 * Nothing here runs under PHP-FPM: {@see self::install()} returns early outside a worker,
 * so an application serving some routes through Octane and the rest through FPM gets the
 * FPM half exactly as before. Octane's events are referred to by name, never imported —
 * `laravel/octane` is the application's choice, not a dependency of the toolkit.
 *
 * A custom worker that does not run Octane — a hand-written FrankenPHP worker script —
 * dispatches none of those events. It calls {@see self::operationStarting()} and
 * {@see self::operationTerminated()} around each request itself.
 */
final class WorkerLifecycle
{
    /** Octane's events that open an operation. Each carries `$app` and `$sandbox`. */
    private const OPENING_EVENTS = [
        'Laravel\Octane\Events\RequestReceived',
        'Laravel\Octane\Events\TaskReceived',
        'Laravel\Octane\Events\TickReceived',
    ];

    /** Implemented by RequestTerminated, TaskTerminated and TickTerminated. */
    private const CLOSING_EVENT = 'Laravel\Octane\Contracts\OperationTerminated';

    /** @var array<string, PackageIsolation> keyed by package short name */
    private array $isolations = [];

    private bool $listenersWarmed = false;

    /** @var array<string, list<string>> short name => namespaces, as the application configured them */
    private readonly array $isolate;

    private static ?ReflectionProperty $providerApplication = null;

    private static ?ReflectionProperty $dispatcherContainer = null;

    /**
     * @param  array<array-key, mixed>  $isolate  `package-toolkit.octane.isolate`
     */
    public function __construct(
        private readonly bool $sandboxProviders = true,
        private readonly bool $sandboxEvents = false,
        array $isolate = [],
    ) {
        $this->isolate = self::normalizeIsolation($isolate);
    }

    /**
     * Install the lifecycle into a long-lived application, once; `null` anywhere else.
     *
     * Called from every toolkit provider's `register()`. The first one in a worker
     * installs; the rest find it bound and get the same instance back.
     */
    public static function install(Application $app): ?self
    {
        if ($app->bound(self::class)) {
            return $app->make(self::class);
        }

        if (! self::enabled($app)) {
            return null;
        }

        $config = $app->make('config');

        $lifecycle = new self(
            sandboxProviders: (bool) $config->get('package-toolkit.octane.sandbox_providers', true),
            sandboxEvents: (bool) $config->get('package-toolkit.octane.sandbox_events', false),
            isolate: (array) $config->get('package-toolkit.octane.isolate', []),
        );

        $app->instance(self::class, $lifecycle);

        $events = $app->make('events');

        foreach (self::OPENING_EVENTS as $event) {
            $events->listen($event, function (object $event) use ($lifecycle): void {
                $base = self::applicationFrom($event, 'app');
                $sandbox = self::applicationFrom($event, 'sandbox');

                if ($base !== null && $sandbox !== null) {
                    $lifecycle->operationStarting($base, $sandbox);
                }
            });
        }

        $events->listen(self::CLOSING_EVENT, function (object $event) use ($lifecycle): void {
            $base = self::applicationFrom($event, 'app');

            if ($base !== null) {
                $lifecycle->operationTerminated($base);
            }
        });

        return $lifecycle;
    }

    /**
     * Whether the toolkit should treat this process as a long-lived worker.
     *
     * `package-toolkit.octane.enabled` decides when the application set it; otherwise the
     * process does ({@see Runtime::detect()}).
     */
    public static function enabled(Application $app): bool
    {
        $configured = $app->bound('config')
            ? $app->make('config')->get('package-toolkit.octane.enabled')
            : null;

        return $configured === null ? Runtime::detect()->isLongLived() : (bool) $configured;
    }

    /**
     * Whether the application asked for the given package to be isolated.
     */
    public function isolates(string $package): bool
    {
        return array_key_exists($package, $this->isolate);
    }

    /**
     * Start isolating a package.
     *
     * @param  list<string>  $namespaces  The provider's own namespace, used when the
     *                                    application named the package without any
     * @param  iterable<string>  $abstracts  What its provider bound
     */
    public function isolate(string $package, array $namespaces, iterable $abstracts): void
    {
        $isolation = $this->isolations[$package] ??= new PackageIsolation(
            $package,
            $this->isolate[$package] !== [] ? $this->isolate[$package] : $namespaces,
        );

        $isolation->own($abstracts);
    }

    /**
     * @return array<string, PackageIsolation>
     */
    public function isolations(): array
    {
        return $this->isolations;
    }

    public function sandboxesProviders(): bool
    {
        return $this->sandboxProviders;
    }

    public function sandboxesEvents(): bool
    {
        return $this->sandboxEvents;
    }

    /**
     * An operation is about to run on `$sandbox`, a clone of the booted `$base`.
     *
     * For a worker that serves requests from the booted application itself, without a
     * clone, pass the same application twice.
     */
    public function operationStarting(Application $base, Application $sandbox): void
    {
        // Before the first operation the original is still exactly what boot left — the
        // clone has been taken, but nothing has run on either yet.
        if ($this->sandboxEvents && ! $this->listenersWarmed) {
            $this->warmSharedListeners($base, $sandbox);
        }

        foreach ($this->isolations as $isolation) {
            if (! $isolation->hasSnapshot()) {
                $isolation->snapshot($base);
            }
        }

        $this->pointAt($sandbox);
    }

    /**
     * The operation is over; `$base` is the booted application the next one is cloned from.
     */
    public function operationTerminated(Application $base): void
    {
        // Resolved on the original from `register()`, so it is one instance for the
        // worker's whole life — and its memo with it, unless forgotten here.
        if ($base->resolved(PublishedAssets::class)) {
            $base->make(PublishedAssets::class)->flush();
        }

        if ($this->isolations !== []) {
            $instances = PackageIsolation::instancesOf($base);
            $declaredClasses = count(get_declared_classes());

            foreach ($this->isolations as $isolation) {
                $isolation->restore($base, $instances, $declaredClasses);
            }
        }

        // Back to the original, so nothing keeps the finished clone alive until the next
        // operation replaces it.
        $this->pointAt($base);
    }

    /**
     * Build every listener bound as a singleton on the worker, once, before the dispatcher
     * is first pointed at a clone.
     *
     * A listener bound as a singleton was meant to be one instance for the worker — all of
     * Octane's own are. Built lazily they would be built on the clone, and so once per
     * operation: measured, that put a seventh on every request of a bare application.
     * Built here they are on the worker, every clone inherits them, and what the clone
     * builds is only what was never meant to be shared — a plain listener class and its
     * dependencies, which is the leak this exists to stop.
     *
     * The first operation's clone was taken before this ran — Octane clones, then
     * dispatches the event that brings us here — so it is handed the same instances.
     */
    private function warmSharedListeners(Application $base, Application $sandbox): void
    {
        $this->listenersWarmed = true;

        $dispatcher = $base->make('events');

        if (! $dispatcher instanceof Dispatcher) {
            return;
        }

        foreach ($dispatcher->getRawListeners() as $listeners) {
            foreach ((array) $listeners as $listener) {
                $class = match (true) {
                    is_string($listener) => explode('@', $listener)[0],
                    is_array($listener) && is_string($listener[0] ?? null) => $listener[0],
                    default => null,
                };

                if ($class !== null && $base->bound($class) && $base->isShared($class) && ! $base->resolved($class)) {
                    $sandbox->instance($class, $base->make($class));
                }
            }
        }
    }

    private function pointAt(Application $app): void
    {
        if ($this->sandboxProviders) {
            self::$providerApplication ??= new ReflectionProperty(ServiceProvider::class, 'app');

            foreach ($app->getProviders(PackageServiceProvider::class) as $provider) {
                self::$providerApplication->setValue($provider, $app);
            }
        }

        if ($this->sandboxEvents && $app->resolved('events')) {
            $dispatcher = $app->make('events');

            if ($dispatcher instanceof Dispatcher) {
                self::$dispatcherContainer ??= new ReflectionProperty(Dispatcher::class, 'container');
                self::$dispatcherContainer->setValue($dispatcher, $app);
            }
        }
    }

    /**
     * The namespace a package's classes live under, told from its service provider.
     *
     * The provider's own namespace, one level up when the provider sits in a `Providers`
     * namespace — the same convention the base path follows for `src/Providers`. Without it
     * a package laid out as `Acme\Invoices\Providers\InvoicesServiceProvider` would be
     * isolated, and diagnosed, as `Acme\Invoices\Providers\` alone: its providers and
     * nothing else.
     *
     * @internal Shared by the providers and `package-toolkit:octane`.
     */
    public static function packageNamespace(object $provider): string
    {
        $namespace = (new ReflectionClass($provider))->getNamespaceName();

        return str_ends_with($namespace, '\\Providers')
            ? substr($namespace, 0, -strlen('\\Providers'))
            : $namespace;
    }

    /**
     * Read an application off one of Octane's events without depending on its classes.
     */
    private static function applicationFrom(object $event, string $property): ?Application
    {
        $value = get_object_vars($event)[$property] ?? null;

        return $value instanceof Application ? $value : null;
    }

    /**
     * `['attendance', 'wire-core' => 'NyonCode\\Wire\\']` → short name => namespaces.
     *
     * @param  array<array-key, mixed>  $isolate
     * @return array<string, list<string>>
     */
    private static function normalizeIsolation(array $isolate): array
    {
        $normalized = [];

        foreach ($isolate as $key => $value) {
            if (is_int($key)) {
                if (is_string($value) && $value !== '') {
                    $normalized[$value] = [];
                }

                continue;
            }

            $namespaces = array_values(array_filter(
                array_map(
                    fn (mixed $namespace): string => is_string($namespace) ? trim($namespace, '\\').'\\' : '',
                    (array) $value,
                ),
                fn (string $namespace): bool => $namespace !== '\\' && $namespace !== '',
            ));

            $normalized[$key] = $namespaces;
        }

        return $normalized;
    }
}
