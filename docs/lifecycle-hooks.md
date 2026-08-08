---
title: Lifecycle hooks
description: Run your own code at the four moments the toolkit wires a package up — registering, registered, booting and booted.
---

# Lifecycle hooks

Four closures, registered on the packager, run at the four points where the provider does its work.
They exist so that small pieces of custom wiring can stay inside `configure()` rather than forcing
an override of `register()` or `boot()`.

```php
$packager
    ->name('Blog')
    ->registeringPackage(function (Packager $packager) {
        // before anything is registered
    })
    ->registeredPackage(function (Packager $packager) {
        // after config, asset mirror and install command are registered
    })
    ->bootingPackage(function (Packager $packager) {
        // before publishing, commands and resources are booted
    })
    ->bootedPackage(function (Packager $packager) {
        // after everything is booted
    });
```

Each callback receives the `Packager`, so it can read anything the chain declared.

## When each one fires

```php
// register()
$this->configure($packager);
$packager->executeConditionalCallbacks();
// ── registering ──────────────────────────────
$this->registerConfig();
$this->registerAssetMirror();
$this->registerInstallCommand();
$this->performAutoInstall();
// ── registered ───────────────────────────────

// boot()
// ── booting ──────────────────────────────────
$this->registerPublishing();
$this->registerPackageCommands();
$this->registerAboutCommand();
$this->bootPackageResources();
// ── booted ───────────────────────────────────
```

Two consequences follow from that ordering, and they are the whole reason to pick one hook over
another:

- **`registering` runs after `configure()` returns.** The chain is complete by then, including any
  [conditional callbacks](/conditional-configuration). There is no hook that fires mid-chain.
- **`booted` is the only hook where every resource is live.** Views are loaded, routes are in the
  router, translations resolve, middleware is registered. Anything that reads a resource belongs
  here.

| Hook | Safe to use | Not yet available |
|---|---|---|
| `registering` | container bindings, `$packager` state | config values from your package |
| `registered` | your merged config, `config()` | views, routes, translations |
| `booting` | everything from `registered` | views, routes, translations, middleware |
| `booted` | everything | — |

## Choosing a hook

### `registeringPackage`

Bind services before anything else touches the container.

```php
$packager->registeringPackage(function () {
    app()->singleton(BlogRepository::class, EloquentBlogRepository::class);
});
```

### `registeredPackage`

Your config has been merged, so this is the first point where `config('blog.…')` is meaningful.

```php
$packager->registeredPackage(function () {
    app()->bind(SearchEngine::class, function () {
        return match (config('blog.search.driver')) {
            'meilisearch' => new MeilisearchEngine(config('blog.search.host')),
            default => new DatabaseEngine(),
        };
    });
});
```

### `bootingPackage`

Anything that must be in place *before* your resources register — a macro your Blade components
depend on, a custom validation rule your routes use.

```php
$packager->bootingPackage(function () {
    Str::macro('excerpt', fn (string $value, int $words = 30) => Str::words($value, $words));
});
```

### `bootedPackage`

The general-purpose hook. Policies, gates, scheduled tasks, morph maps, macros on things that only
exist once the framework has booted.

```php
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;

$packager->bootedPackage(function () {
    Gate::policy(Post::class, PostPolicy::class);

    Relation::enforceMorphMap([
        'post' => Post::class,
        'comment' => Comment::class,
    ]);

    app()->booted(function () {
        app(Schedule::class)->command('blog:prune')->daily();
    });
});
```

:::note Scheduling needs one more layer
`Schedule` is resolved after all providers boot, so registering a scheduled command from
`bootedPackage` still needs the `app()->booted()` wrapper above. That is Laravel's ordering, not the
toolkit's.
:::

## Hooks and provider methods have the same names

`PackageServiceProvider` has four methods called `registeringPackage()`, `registeredPackage()`,
`bootingPackage()` and `bootedPackage()` — with no arguments. Those are what *fire* the hooks. The
identically named methods on `Packager` take a `Closure` and *register* them.

Both are usable, and they are not alternatives — overriding the provider method without calling
`parent::` stops the closure from ever running:

```php
// ✗ the closure registered in configure() never fires
public function bootedPackage(): void
{
    Gate::policy(Post::class, PostPolicy::class);
}

// ✓
public function bootedPackage(): void
{
    parent::bootedPackage(); // [tl! focus]

    Gate::policy(Post::class, PostPolicy::class);
}
```

Prefer the closure form. It keeps the whole description of the package in one place, and it cannot
be broken by a forgotten `parent::`.

## One closure per hook

Registering a hook twice replaces the first:

```php
$packager
    ->bootedPackage(fn () => Gate::policy(Post::class, PostPolicy::class))
    ->bootedPackage(fn () => Gate::policy(Comment::class, CommentPolicy::class));

// Only the comment policy is registered.
```

Combine them into one closure instead — or, if the two pieces belong to genuinely different
concerns, use [conditional configuration](/conditional-configuration), which accumulates.

## Exceptions inside a hook

Lifecycle hook closures are not wrapped in a `try`. An exception thrown inside one propagates out of
`register()` or `boot()` and takes the request with it. That is intentional: a package that cannot
wire itself up should fail loudly.

This is the opposite of the [conditional callbacks](/conditional-configuration#error-handling),
which report and continue. If you want that behaviour in a hook, do it yourself:

```php
$packager->bootedPackage(function () {
    try {
        BlogSearchIndex::warm();
    } catch (Throwable $e) {
        report($e);
    }
});
```
