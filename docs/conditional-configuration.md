---
title: Conditional configuration
description: Register resources only in certain environments, only when a class exists, or only in the console.
---

# Conditional configuration

Not every package should register everything, everywhere. Debug routes belong in local development.
An integration with another package should only wire itself up when that package is installed. A
maintenance command has no business existing during a web request.

The packager's conditional builders express that inside the same chain as everything else.

```php
$packager
    ->name('Blog')
    ->hasConfig()
    ->hasRoutes(['api.php'])
    ->whenLocal(function (Packager $packager) {
        $packager->hasRoutes(['api.php', 'debug.php']);
    })
    ->whenClassExists(Livewire\Livewire::class, function (Packager $packager) {
        $packager->hasComponents('blog', LivewirePostList::class);
    });
```

## When callbacks run

Each conditional builder evaluates its condition **immediately**, and queues the callback only if it
passed. The queue is drained by the provider straight after `configure()` returns:

```php
$this->configure($this->packager);              // conditions evaluated, callbacks queued
$this->packager->executeConditionalCallbacks(); // callbacks run here
$this->validatePackageConfiguration();
```

Two things follow from this. First, callbacks run in the order they were registered, and a later one
sees whatever an earlier one did. Second, they run *after* the whole chain — so a callback can
override something declared further down:

```php
$packager
    ->name('Blog')
    ->whenLocal(fn (Packager $p) => $p->hasRoutes(['api.php', 'debug.php']))
    ->hasRoutes(['api.php']);

// Locally: api.php + debug.php. The callback runs last and replaces the set.
```

Because `hasRoutes()` *replaces* the file set while `hasCommands()` *appends* to it, the effect of
running last differs per resource. Check the page for the resource you are toggling if the
distinction matters.

## The builders

### `when()` and `unless()`

```php
$packager
    ->when(config('blog.api.enabled'), fn (Packager $p) => $p->hasRoutes(['api.php']))
    ->unless(app()->runningUnitTests(), fn (Packager $p) => $p->hasAssets());
```

`unless()` is `when(! $condition, …)` — nothing more.

:::warning `config()` inside a condition
Your package's own config has not been merged yet when `configure()` runs — that happens in
`registerConfig()`, later in `register()`. `config('blog.api.enabled')` therefore reads only what
the application published, and is `null` if the consumer never published the file. Use an
environment variable, a `whenClassExists()` check, or move the decision into the
[`registeredPackage` hook](/lifecycle-hooks#registeredpackage) where config is available.
:::

### Environments

```php
$packager
    ->whenEnvironment('staging', fn (Packager $p) => $p->hasRoutes(['debug.php']))
    ->whenEnvironment(['local', 'testing'], fn (Packager $p) => $p->hasSeeders())
    ->whenProduction(fn (Packager $p) => $p->hasOptimizeCommands('blog:cache', 'blog:clear'))
    ->whenLocal(fn (Packager $p) => $p->hasStubs());
```

`whenLocal()` matches **both** `local` and `development`. `whenProduction()` matches `production`.

The current environment is resolved defensively, in this order: `app()->environment()`, then
`config('app.env')`, then the `APP_ENV` / `ENVIRONMENT` variables — and if none of those answer, it
falls back to `'production'`. That default is deliberately the restrictive one: a package whose
environment cannot be determined should behave as though it is live.

### Class, function and extension checks

```php
$packager
    ->whenClassExists(Laravel\Horizon\Horizon::class, function (Packager $p) {
        $p->hasConfig(['blog-horizon.php']);
    })
    ->whenFunctionExists('imagecreatetruecolor', function (Packager $p) {
        $p->hasCommands([GenerateThumbnails::class]);
    })
    ->whenExtensionLoaded('redis', function (Packager $p) {
        $p->hasOptimizeCommands('blog:cache-warm');
    });
```

`whenClassExists()` is the idiomatic way to build optional integrations. It works because Composer's
autoloader answers `class_exists()` without the class having to be loaded already.

### Console

```php
$packager->whenConsole(function (Packager $packager) {
    $packager->hasCommands()->hasStubs();
});
```

The check is `php_sapi_name() === 'cli' || app()->runningInConsole()`, so it also covers artisan
running through a non-CLI SAPI.

:::note Commands are already console-guarded
`registerPackageCommands()` returns early unless `runningInConsole()`. Wrapping `hasCommands()` in
`whenConsole()` saves the file discovery, not the registration — worth it for a package with many
commands, pointless for a package with two.
:::

### `whenMultiple()`

For a table of conditions built elsewhere — a compatibility matrix, say:

```php
$packager->whenMultiple([
    [
        'condition' => class_exists(Livewire\Livewire::class),
        'callback' => fn (Packager $p) => $p->hasViews(directory: '../resources/views/livewire'),
    ],
    [
        'condition' => version_compare(app()->version(), '13.0', '>='),
        'callback' => fn (Packager $p) => $p->hasConfig(['blog-13.php']),
    ],
]);
```

Entries missing either key are skipped silently.

## Error handling

A callback that throws does **not** take the package down. The exception is passed to `report()` — or
to `error_log()` when the application has not booted far enough for `report()` to exist — and the
remaining callbacks still run.

```php
$packager
    ->whenLocal(function () {
        throw new RuntimeException('boom');   // reported, then execution continues
    })
    ->whenLocal(fn (Packager $p) => $p->hasSeeders());  // still runs
```

This is the right trade for optional configuration: a broken integration with a third-party package
should degrade that integration, not break the application. It does mean a silently missing resource
is worth checking your log for.

If you want a failure to be fatal, use a [lifecycle hook](/lifecycle-hooks#exceptions-inside-a-hook)
instead — those are not wrapped.

## Introspection

```php
$packager->conditionalCallbacksExecuted();        // bool
$packager->getPendingConditionalCallbacksCount(); // int — 0 once drained
$packager->resetConditionalCallbacks();           // clear the queue and the flag
```

`executeConditionalCallbacks()` is idempotent: the second call returns immediately. `reset` exists
for tests that reuse a packager across cases.

## Recipes

### Optional integration with another package

```php
$packager
    ->name('Blog')
    ->hasConfig()
    ->whenClassExists(Spatie\MediaLibrary\MediaCollections\Models\Media::class, function (Packager $p) {
        $p->hasMigrations(['create_blog_media_table.php'])
            ->hasConfig(['blog.php', 'blog-media.php']);
    });
```

### Development-only tooling

```php
$packager
    ->name('Blog')
    ->hasConfig()
    ->hasRoutes(['api.php'])
    ->whenLocal(function (Packager $p) {
        $p->hasRoutes(['api.php', 'debug.php'])   // [tl! ++]
            ->hasSeeders()                        // [tl! ++]
            ->hasFactories()                      // [tl! ++]
            ->hasStubs();                         // [tl! ++]
    });
```

### Feature flag from the environment

```php
$packager->when(
    filter_var(env('BLOG_API_ENABLED', true), FILTER_VALIDATE_BOOL),
    fn (Packager $p) => $p->hasRoutes(['api.php']),
);
```

:::warning `env()` and cached config
The usual Laravel caveat applies in full here: once the consumer runs `php artisan config:cache`,
Laravel stops loading the `.env` file, and `env()` returns only its default. A flag read this way
will quietly revert to `true` in exactly the environment where it matters most.

If the flag has to survive `config:cache`, publish a config file for it and make the decision in
the [`registeredPackage` hook](/lifecycle-hooks#registeredpackage), which runs after your config is
merged:

```php
$packager
    ->name('Blog')
    ->hasConfig()
    ->registeredPackage(function () {
        if (! config('blog.api.enabled')) {
            return;
        }

        // …bind or register the API-only pieces here
    });
```
:::
