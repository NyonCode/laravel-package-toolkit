---
title: Octane and long-lived workers
description: Serve packages written for PHP-FPM from Octane — Swoole, RoadRunner or FrankenPHP — without changing them, and find what still needs the package's attention.
---

# Octane and long-lived workers

Added in **2.6.0**. Nothing to call: install the package, run the application under
[Laravel Octane](https://laravel.com/docs/octane), and every package built on the toolkit is pointed
at the right container for each request.

```bash
php artisan octane:start --server=frankenphp   # or swoole, roadrunner
php artisan package-toolkit:octane             # what a worker would keep from request to request
```

Under PHP-FPM a package gets a fresh process for every request, so whatever it leaves behind is
gone before the next one. Octane boots the application **once per worker** and serves every request
from a clone of it. That is where the speed comes from — routes, views, translations, config and
every registry a package filled at boot are paid for once — and also where a package written for
FPM can leak state from one request into the next without ever having done anything wrong.

## What the toolkit does for you

Octane already points `app()`, the facades and a list of framework services (router, view factory,
validator, gate and others) at the clone. Two things it does not, and both are common in packages:

| Pattern in a package | Without the toolkit | With it |
|---|---|---|
| `$this->app->make(Foo::class)` in a route, composer, hook or listener closure | resolves on the worker; a singleton made that way is shared by every later request | resolves on the request's clone |
| `$this->app['config']->get(…)` in such a closure | reads the worker's config, not the request's copy | reads the request's config |
| `Event::listen(Ping::class, Listener::class)` where the listener takes a singleton | the singleton is built on the worker and shared | opt in to [`sandbox_events`](#the-event-dispatcher) |
| static properties, objects built at boot | shared by every request | [isolate the package](#isolating-a-package), or fix it in the package |

**Every toolkit provider's `$this->app` is the request's clone while the request runs**, and the
booted application again once it is over. The same happens for Octane's tasks and ticks. A provider
that is not built on the toolkit is not touched.

**The asset mirror's per-request memo is forgotten after every request**, so a deploy's new
`?id=` reaches the page without anyone calling
[`PublishedAssets::flush()`](/assets#flush-for-long-lived-workers).

### What a worker keeps on purpose

A singleton first resolved **during** a request is resolved on the clone and dies with it — under
Octane it is already one per request, whether it is bound with `singleton()`, `scoped()` or
`#[Singleton]`. What a worker keeps is what existed **after boot**: anything a provider resolved
from `register()` or `boot()`, and anything listed in `octane.warm`. That is a registry, a manager,
a parsed manifest — the reason to boot once — and the toolkit leaves all of it alone.

## The event dispatcher

```php title="config/package-toolkit.php"
return [
    'octane' => [
        'sandbox_events' => true,
    ],
];
```

Laravel's event dispatcher builds a class listener through its own container — the worker's — and
Octane does not point it at the clone. A listener's constructor dependencies therefore outlive the
request they were built for. With `sandbox_events` the dispatcher follows the request too.

Off by default because it is not limited to packages: it changes how **every** class listener in
the application is built. A listener bound as a singleton (all of Octane's own are) stays one
instance for the worker — those are built once, before the first request — so what becomes per
request is only a plain listener class and its dependencies — the price of not sharing them, and
nothing else: the switch itself measured under a microsecond per request.
[`package-toolkit:octane`](#finding-what-is-left) says whether you need it.

## Isolating a package

```php title="config/package-toolkit.php"
return [
    'octane' => [
        'isolate' => [
            'attendance',                       // the package's short name
            'wire-core' => 'NyonCode\\Wire\\',  // or with the namespaces to cover
        ],
    ],
];
```

For a package you do not trust under a worker yet. After every request the toolkit puts it back the
way the booted worker had it:

- **instances it left on the worker's container are forgotten** — anything under its namespace, and
  anything its provider bound under another name (a framework contract, a string key). Instances
  that were there when the worker finished booting are kept: a registry filled from `boot()` stays
  filled.
- **static properties of its classes are restored** to the value they had after boot, or to their
  declared default for a class first loaded later.

The namespace defaults to the namespace of the package's service provider — one level up when the
provider sits in a `Providers` namespace, so `Acme\Invoices\Providers\InvoicesServiceProvider`
covers `Acme\Invoices\`, the same convention the toolkit follows for `src/Providers`. Give it explicitly when
the package's classes live elsewhere, or under several roots. A wide namespace is safe:
`'wire-core' => 'NyonCode\\'` covers the toolkit's namespace too, and the toolkit's own classes —
the asset mirror, the lifecycle itself — are recognised by where their files are and left alone. A
namespace that would cover `Illuminate\`, `Laravel\` or `Symfony\` is ignored outright; isolating
the framework would throw away exactly what a worker boots once to keep.

The restore itself is cheap — about 23 µs per request for four isolated packages, measured with
real packages loaded ([Performance](#performance)). The real price is the one PHP-FPM always paid: a
static cache the package keeps is rebuilt per request rather than per worker, which is why isolation
is per package and not the default.

:::warning What isolation cannot undo
An object that took per-request state **while the worker booted** — the console request a worker
boots with, the worker's container, its config — is kept, because it was there after boot. A
restored static is the same instance it was after boot; changes made inside that object are not
undone. `package-toolkit:octane` lists both kinds; only the package can stop doing it.
:::

## Finding what is left

```bash
php artisan package-toolkit:octane
php artisan package-toolkit:octane --all      # scalar statics too
php artisan package-toolkit:octane --strict   # non-zero exit when something needs attention, for CI
```

```text title="output"
 Worker configuration ............................................................
 Providers follow the operation ............................................... on
 Event dispatcher follows the operation ...................................... off
 Isolated packages ......................................................... none

 invoices ............................................................ Acme\Invoices
 Nothing found ................................................................ ok

 blog ...................................................................... Acme\Blog
 held from boot Support\Locale .............................. takes Request $request
 class listener Listeners\IndexPost ..................... shared Search\SearchClient
 static Markdown\Parser::$cache .............................................. array
```

Run it from the console, where the application boots the way a worker does. For every package built
on the toolkit it reports:

- **held from boot** — an object the container already holds whose constructor took a request,
  the container, the config repository, the session, a guard or a route. It is shared by every
  request, and was built against the console request the worker booted with.
- **class listener** — a listener of the package that takes a shared binding. Gone once
  `sandbox_events` is on.
- **static** — a static property that holds an array or an object. Often a cache, which is exactly
  what a worker is good for — but a cache that only grows is a memory leak until Octane recycles the
  worker (`--max-requests`, 500 by default), and a captured object is shared state.

## Performance

Measured on one machine (Intel i7-8700B, macOS), one PHP 8.4 binary with the same OPcache settings,
4 workers each, ApacheBench at concurrency 32, median of five runs. The application is a bare
Laravel 13 with four toolkit packages installed. *Classic* is PHP's built-in server booting the
framework on every request, the way PHP-FPM does; *Octane* is RoadRunner.

| Route | Classic | Octane, toolkit support off | Octane, defaults | Octane, `sandbox_events` + 4 isolated |
|---|---|---|---|---|
| `/up` | 409 req/s | 1160 req/s (2.8×) | 1137 req/s (2.8×) | 1068 req/s (2.6×) |
| a route rendering a view, firing an event | 374 req/s | 1071 req/s (2.9×) | 1034 req/s (2.8×) | 973 req/s (2.6×) |

The differences between the three Octane columns are inside the run-to-run spread of the
benchmark (±5%) — repeated with seven rounds, the order of the columns changed. What the toolkit
adds is measured directly, on a booted worker:

| Per request | |
|---|---|
| pointing toolkit providers at the request and back | ~9 µs |
| `sandbox_events` | < 1 µs, plus building the listeners that are no longer shared |
| isolating four packages | ~23 µs |

So the gain is Octane's — boot once, serve many — and the toolkit's share of a request is in the
tens of microseconds. Behind Docker on macOS, where PHP-FPM also pays for file sharing on every
request, the same comparison against PHP-FPM came out at 3–4× for RoadRunner, 4–5× for Swoole and
6–10× for FrankenPHP; that says more about the bind mount than about any runtime, but it is the
setup many people develop on.

## Running part of the application on PHP-FPM

Octane is decided per process, not per application. Nothing here runs unless the process is an
Octane worker (`LARAVEL_OCTANE`, which all three `octane:start` commands set), so the same code
can serve some routes through Octane and the rest through FPM:

```nginx
location /attendance/ { try_files $uri @fpm; }      # a package that is not ready yet
location /            { try_files $uri @octane; }

location @fpm    { include fastcgi_params; fastcgi_pass unix:/run/php/php-fpm.sock;
                   fastcgi_param SCRIPT_FILENAME $document_root/index.php; }
location @octane { proxy_pass http://127.0.0.1:8000; proxy_set_header Host $host; }
```

Sessions, cache and queues have to be shared stores (Redis, the database), and a deploy has to
reload both sides — `php artisan octane:reload` and a PHP-FPM reload for OPcache.

## Configuration reference

| Key | Default | |
|---|---|---|
| `package-toolkit.octane.enabled` | `null` | `null` detects the worker; `true`/`false` overrides |
| `package-toolkit.octane.sandbox_providers` | `true` | point toolkit providers at each operation's clone |
| `package-toolkit.octane.sandbox_events` | `false` | point the event dispatcher at it too |
| `package-toolkit.octane.isolate` | `[]` | short names, or short name => namespace(s) |

The toolkit ships no config file — create `config/package-toolkit.php` in the application when you
need one. It is read while providers register, so a cached configuration works as usual.

## A worker that is not Octane

A FrankenPHP worker script of your own dispatches none of Octane's events. Call the lifecycle around
each request yourself:

```php title="public/worker.php"
$lifecycle = $app->make(\NyonCode\LaravelPackageToolkit\Support\Worker\WorkerLifecycle::class);

$handler = static function () use ($app, $kernel, $lifecycle) {
    $lifecycle->operationStarting($app, $app);
    // … handle the request with $kernel …
    $lifecycle->operationTerminated($app);
};

while (frankenphp_handle_request($handler)) {
    gc_collect_cycles();
}
```

FrankenPHP's worker mode is detected from the `FRANKENPHP_WORKER` variable it sets; set
`package-toolkit.octane.enabled` to `true` for any other server. Without a clone per request,
Octane's own resets — auth, session, locale and the rest — are yours to do too.
