---
title: The service provider
description: What PackageServiceProvider does during register() and boot(), in the exact order it does it, and every hook you can override.
---

# The service provider

`PackageServiceProvider` is an ordinary Laravel `ServiceProvider` with one abstract method and a
fixed sequence of work built on top of it. Understanding that sequence is most of what you need to
know to extend it, because every step is a `protected` or `public` method you can override.

```php
abstract class PackageServiceProvider extends ServiceProvider implements ProvidesPackageServices
{
    abstract public function configure(Packager $packager): void;
}
```

## What runs during `register()`

```php
public function register(): void
{
    $this->packager = $this->bootPackager();          // 1
    $this->validatePackager();                        // 2
    $this->packager->hasBasePath($this->getPackageBaseDir()); // 3

    $this->configure($this->packager);                // 4
    $this->packager->executeConditionalCallbacks();   // 5
    $this->validatePackageConfiguration();            // 6

    $this->registeringPackage();                      // 7

    $this->registerConfig();                          // 8
    $this->registerAssetMirror();                     // 9
    $this->registerInstallCommand();                  // 10
    $this->performAutoInstall();                      // 11

    $this->registeredPackage();                       // 12
}
```

1. **`bootPackager()`** returns a fresh `Packager`. Override it to return a subclass if you want to
   add your own `hasX()` builders.
2. **`validatePackager()`** throws `PackageConfigurationException` if that returned `null`.
3. **`getPackageBaseDir()`** reflects on your provider class to find its own file, and takes the
   directory. That directory becomes the root every relative path is resolved against — see
   [The Packager](/packager#paths).
4. **`configure()`** — your method. This is where the whole package is described.
5. **Conditional callbacks** registered with `when()`, `whenEnvironment()` and friends run here —
   *after* `configure()` returns, so they see the fully built chain. See
   [Conditional configuration](/conditional-configuration).
6. **`validatePackageConfiguration()`** throws `MissingNameException` if `name()` was never called.
7. **`registeringPackage()`** fires the `registering` [lifecycle hook](/lifecycle-hooks).
8. **`registerConfig()`** requires each config file, checks it returns an array, and calls
   `mergeConfigFrom()`. A file that does not return an array throws `InvalidReturnTypeException`.
9. **`registerAssetMirror()`** declares the package's asset directory with the shared
   [`PublishedAssets`](/assets#the-asset-mirror) singleton. Bookkeeping only — nothing is copied here.
10. **`registerPackageAssets()`** declares the entries a template renders — with the Vite base and
    any [`hasAssetFallback()`](/assets#keeping-the-tag-hasassetfallback) resolver — to the shared
    [`PackageAssets`](/assets#rendering-them-in-a-template) singleton. Also bookkeeping — no manifest
    is read and no file is touched until a tag is actually rendered.
11. **`registerInstallCommand()`** registers the install command, but only when the package is
    installable *and* the application is running in the console.
12. **`performAutoInstall()`** schedules a silent installation on `app.booted` when
    `installOnRun()` was set.
13. **`registeredPackage()`** fires the `registered` lifecycle hook.

:::note Why config is merged in `register()`
Laravel expects `mergeConfigFrom()` in `register()` so that other providers booting after yours
already see your defaults. Publishing, by contrast, belongs in `boot()`, and that is where the
toolkit does it.
:::

## What runs during `boot()`

```php
public function boot(): void
{
    $this->bootingPackage();            // booting lifecycle hook

    $this->registerPublishing();        // every publishX() below
    $this->registerPackageCommands();   // console only
    $this->registerAboutCommand();      // the toolkit's own `about` section
    $this->bootPackageResources();      // every bootX() below

    $this->bootedPackage();             // booted lifecycle hook
}
```

### `registerPublishing()`

Guarded on `runningInConsole()` — a web request never builds a publish map. It calls, in order:

`publishAssets()` · `publishConfig()` · `publishFactories()` · `publishMigrations()` ·
`publishProvider()` · `publishRoutes()` · `publishSeeders()` · `publishStubs()` ·
`publishTranslations()` · `publishViewComponentNamespaces()` · `publishViewComponents()` ·
`publishViews()`

Each one returns early if the matching resource was never declared, so the cost of a resource you
do not use is one boolean check. Full destinations and tag names are on [Publishing](/publishing).

### `bootPackageResources()`

```php
$this->bootAboutCommand()
    ->bootAssets()
    ->bootMigrations()
    ->bootRoutes()
    ->bootBroadcastChannels()
    ->bootMiddleware()
    ->bootEvents()
    ->bootOptimizes()
    ->bootSharedViewData()
    ->bootTranslations()
    ->bootViewComposers()
    ->bootViewComponentNamespaces()
    ->bootViewComponents()
    ->bootViews();
```

The chain is fluent, so overriding one link and calling `parent::` keeps the rest intact.

## Hooks you can override

### `packageCommands()`

Returns commands to register *in addition* to whatever `hasCommands()` discovered. Useful for a
command that needs constructor arguments, since `hasCommands()` deals in class strings.

```php
use Illuminate\Console\Command;

public function packageCommands(): array
{
    return [
        new ImportCommand($this->app->make(Importer::class)),
        PruneCommand::class,
    ];
}
```

### `aboutData()`

Extra rows for your package's section in `php artisan about`. Values may be strings or closures;
closures are evaluated lazily, which matters for anything that touches the database or config.

```php
public function aboutData(): array
{
    return [
        'Driver' => fn () => config('blog.driver'),
        'Posts' => fn () => (string) Post::count(),
    ];
}
```

This only appears if you also called `hasAbout()` on the packager — see
[The about command](/about-command).

### `bootPackager()`

Return your own `Packager` subclass to add package-specific builders:

```php
class BlogPackager extends Packager
{
    public function hasSearchIndex(string $driver): static
    {
        $this->searchDriver = $driver;

        return $this;
    }
}

class BlogServiceProvider extends PackageServiceProvider
{
    public function bootPackager(): Packager
    {
        return new BlogPackager();
    }

    public function configure(Packager $packager): void
    {
        // The parameter is typed as Packager, so narrow it if your
        // static analysis needs to see the subclass:
        /** @var BlogPackager $packager */
        $packager->name('Blog')->hasSearchIndex('meilisearch');
    }
}
```

### `getPackageBaseDir()`

Override it if your provider does not live where your resources are. The default reflects on
`static::class`, so it follows subclassing correctly.

## Registering your own bindings

`configure()` describes resources; it is not where you bind services. Do that by overriding
`register()` or `boot()` and calling `parent::` — the toolkit's own work is all inside those two
methods, so it composes normally.

```php
public function register(): void
{
    parent::register(); // [tl! focus]

    $this->app->singleton(PostRepository::class, function ($app) {
        return new EloquentPostRepository($app['db']);
    });
}

public function boot(): void
{
    parent::boot(); // [tl! focus]

    Gate::policy(Post::class, PostPolicy::class);
}
```

Forgetting `parent::` is the one way to break the toolkit: nothing at all gets wired up, and the
failure is silent. If a package suddenly stops registering its views, check for a `register()` or
`boot()` override missing its `parent::` call.

:::tip Prefer lifecycle hooks for small additions
For a handful of statements, the [lifecycle hooks](/lifecycle-hooks) keep everything inside
`configure()` and read better than an override.
:::

## Contracts

| Interface | Declares |
|---|---|
| `Contracts\Packable` | `configure()`, the four lifecycle methods, `aboutData()` |
| `Contracts\ProvidesPackageServices` | extends `Packable`, adds `register()`, `boot()`, `packageCommands()` |
| `Contracts\HasAbout` | **Deprecated.** Removed in 3.0 — `Packable` already declares `aboutData()` |

`PackageServiceProvider` implements `ProvidesPackageServices`, so implementing `Packable` on your
own provider is documentation rather than a requirement.

## Exceptions

| Exception | Thrown when |
|---|---|
| `MissingNameException` | `name()` was never called |
| `PackageConfigurationException` | the packager is `null`, or the provider's own file cannot be reflected |
| `InvalidReturnTypeException` | a config file does not return an array |
| `InvalidLanguageDirectoryException` | a translation subdirectory is not a known language code |
| `Illuminate\Contracts\Filesystem\FileNotFoundException` | a named resource file does not exist |
| `Symfony\…\DirectoryNotFoundException` | a resource directory does not exist or is unreadable |

All of them fire during `register()` or `boot()`, which is to say: on the first request or artisan
call after installation, not months later.
