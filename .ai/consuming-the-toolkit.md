# Using laravel-package-toolkit (consumer reference for AI agents)

Audience: an AI agent working **in another project** that wants to build a Laravel package with `nyoncode/laravel-package-toolkit`. This is the complete public API, extracted from source. Everything is a fluent builder — every `Packager` method returns `static`, so chain freely.

## Mental model

1. Create a service provider that **extends `PackageServiceProvider`** and implement one method: `configure(Packager $packager)`.
2. Inside `configure()`, declare what your package *has* by calling `hasX()` methods on `$packager`. Order does not matter; declaration is decoupled from the boot pipeline.
3. The toolkit resolves paths **relative to the directory of your service provider file** (via reflection). If the provider lives in `src/Providers/`, the base path is auto-corrected up to `src/`'s parent. This is why defaults look like `../config`, `../routes`, `../resources/views`.
4. `name()` is the only required call. Without it registration throws `MissingNameException`.

Minimal package:

```php
use NyonCode\LaravelPackageToolkit\PackageServiceProvider;
use NyonCode\LaravelPackageToolkit\Packager;

class MyPackageServiceProvider extends PackageServiceProvider
{
    public function configure(Packager $packager): void
    {
        $packager
            ->name('My Package')
            ->hasConfig()
            ->hasRoutes()
            ->hasMigrations()
            ->hasTranslations()
            ->hasViews();
    }
}
```

Register it in `composer.json` (`extra.laravel.providers`) as usual for a Laravel package.

## Naming

| Method | Signature | Notes |
|---|---|---|
| `name` | `name(string $name): static` | **Required.** Throws `InvalidArgumentException` if empty. |
| `hasShortName` | `hasShortName(string $shortName): static` | Override the auto-generated slug. Must be kebab-case (`^[a-z0-9-]+$`) or throws. |
| `shortName` | `shortName(): string` | Read the slug. Defaults to `Str::kebab($name)`. Used as the view namespace, translation namespace, and publish-tag prefix. |

## Config

- `hasConfig(string|array|null $configFiles = null, string $directory = '../config'): static`
- `null` → auto-discover **all** files in `../config`. Pass a filename or array of filenames to select specific ones.
- Each config file **must `return` an array** or registration throws `InvalidReturnTypeException`.
- Config is merged (`mergeConfigFrom`) under the file's basename as the key.

## Routes

- `hasRoutes(array|string|null $routeFiles = null, string $directory = '../routes'): static`
- `null` → load all route files in `../routes`; or pass specific filenames (`['api.php', 'web.php']`).
- Loaded via `loadRoutesFrom` at boot.

## Migrations

- `hasMigrations(?array $migrationFiles = null, string $directory = '../database/migrations'): static`
- Supports both timestamped (`2025_01_01_000000_create_x.php`) and **timeless** (`create_x.php`) migrations. Timeless ones get a sequential timestamp prefix automatically **when published** (`getMigrationPublishMapping()`).
- `canLoadMigrations(bool $value = true): static` — when true, migrations are *loaded from the package* at boot (run without publishing). Default is off; call this to enable run-in-place.

## Translations

- `hasTranslations(string $translationPath = 'lang'): static` — loads both PHP and JSON translations. PHP translations are namespaced under `shortName()` (e.g. `__('my-package::messages.key')`); JSON translations are global.

## Views

- `hasViews(?string $viewsPath = null, string $directory = '../resources/views', ?string $namespace = null): static`
- Views are registered under the `shortName()` namespace (e.g. `view('my-package::index')`) unless you pass an explicit `$namespace`.
- `$viewsPath` accepts absolute or relative paths; throws `DirectoryNotFoundException` if missing.

### View components

- `hasComponent(string $prefix, string $componentClass, string $alias = ''): static` — one component.
- `hasComponents(string $prefix, array|string $components): static` — many. String keys in the array become aliases: `['modal' => Modal::class]`.

### View component namespaces

- `hasComponentNamespace(string $prefix, string $namespace): static`
- `hasComponentNamespaces(array $namespaces): static` — `['prefix' => 'App\\View\\Components']`.

### View composers

- `hasViewComposer(string|array $views, string|Closure $composer): static` — bind data when a view renders. `$composer` is a class name or a closure.

### Shared view data

- `hasSharedDataForAllViews(array $viewSharedData): static` — `View::share()` for every key/value.

## Middleware

- `hasMiddlewareAliases(array $aliases): static` — `['role' => EnsureRole::class]`.
- `hasMiddlewareGroups(array $groups): static` — `['api' => [Throttle::class, ...]]`, pushed to the group.
- `hasMiddlewareGlobals(array $middlewares): static` — pushed to the HTTP kernel globally.

## Events

Register event listeners and subscribers; applied at boot via the `Event` facade.

- `hasEvents(array $events): static` — a map of `event => listener(s)`. Each value is a class-string, a closure, or an array of them: `[OrderPlaced::class => [SendMail::class, LogOrder::class]]`.
- `hasEvent(string $event, string|Closure|array $listeners): static` — one event.
- `hasSubscribers(array $subscribers): static` / `hasSubscriber(string $subscriber): static` — event subscriber classes (registered via `Event::subscribe`).

```php
$packager
    ->hasEvent(OrderPlaced::class, SendOrderConfirmation::class)
    ->hasSubscriber(OrderEventSubscriber::class);
```

## Optimize

Register artisan commands that run with `php artisan optimize` (cache warmup) and `php artisan optimize:clear` — the toolkit forwards to Laravel's `ServiceProvider::optimizes()`.

- `hasOptimizeCommands(?string $optimize = null, ?string $clear = null, ?string $key = null): static`
- At least one of `$optimize`/`$clear` must be set (otherwise it's a no-op). `$key` defaults to the package short name at boot.
- Registering **multiple** entries: give each a distinct `$key` — entries sharing a key overwrite one another (Laravel keys optimize commands by provider).

```php
$packager->hasOptimizeCommands(
    optimize: 'my-package:cache',
    clear: 'my-package:clear',
);
```

## Commands

- `hasCommands(string|array|null $commandsClass = null, string $directory = 'Commands'): static` — register command classes, or auto-discover from a directory when `null`.
- `hasCommand(string $commandClass): static` — a single command.
- You can also override `public function packageCommands(): array` on the service provider to return commands.

## Assets

- `hasAssets(string $directory = 'dist'): static` — mark a directory of built assets as publishable.

## Providers (extra service providers)

- `hasProvider(string $provider): static`
- `hasProviders(array $providers): static`
- Used with the install command to copy/register additional providers into the consuming app.

## About command

Adds a section to `php artisan about`.

- `hasAbout(bool $value = true): static`
- `hasVersion(string $version): static` — otherwise the installed composer version is auto-detected.
- Override `public function aboutData(): array` on the service provider to add custom `key => string|Closure` rows.

## Conditional loading

Guards run **immediately after** `configure()`. The callback receives the `Packager`.

- `when(bool $condition, Closure $callback)` / `unless(bool $condition, Closure $callback)`
- `whenMultiple(array $conditions)` — array of `[bool, Closure]` pairs.
- `whenEnvironment(string|array $environments, Closure $callback)`, `whenProduction(Closure)`, `whenLocal(Closure)`, `whenConsole(Closure)`
- `whenClassExists(string $class, Closure)`, `whenFunctionExists(string $function, Closure)`, `whenExtensionLoaded(string $extension, Closure)`

```php
$packager->whenLocal(fn (Packager $p) => $p->hasRoutes('dev.php'));
```

## Lifecycle hooks

Register callbacks that fire at provider lifecycle points. Each receives the `Packager`.

- `registeringPackage(Closure)` — before `register()` work
- `registeredPackage(Closure)` — after register
- `bootingPackage(Closure)` — before boot
- `bootedPackage(Closure)` — after boot

(These four `Packager` methods set callbacks. The service provider has same-named methods that *fire* them — don't confuse the two; as a consumer you call them on `$packager`.)

## Install command

Enable an interactive `php artisan <short-name>:install` command.

Toggles on `Packager`:
- `hasInstallCommand(?Closure $callback = null)` — enable; the closure configures the `InstallCommand` (see publish methods below).
- Presets: `hasQuickInstall()` (config+migrations+assets), `hasFullInstall()` (everything), `hasMinimalInstall()` (config only), `hasDevInstall()` (config+migrations+views+assets, routes only in local).
- `installCommandName(string $name)` — rename (prefixed with short name → `short-name:name`).
- `installCommandHidden(bool $hidden = true)` — hide from `artisan list`.
- `installOnRun(bool = true)`, `installOnRunInEnvironment(string|array)`, `installOnRunInLocal()`, `installOnRunInProduction()` — run install silently on boot.
- `withoutInstallCommand()` — disable.

Inside the install-command callback (`InstallCommand` methods), select what to publish:
- Per-resource: `publishConfig()`, `publishMigrations()`, `publishRoutes()`, `publishViews()`, `publishAssets()`, `publishTranslations()`, `publishProviders()`, `publishComponents()`, `publishComponentNamespaces()` (plus verbose aliases like `publishConfigFiles()`, `publishLanguageFiles()`, `publishServiceProviders()`…).
- Bulk: `publishEverything()` / `publishAll()`, `publishEssentials()`.
- Conditional: `publishIf(bool, ...$tags)`, `publishUnless(bool, ...$tags)`, `publishForEnvironment(string|array, ...$tags)`, `publishForProduction(...$tags)`, `publishForLocal(...$tags)`, `publishCustom(...$tags)`.
- Hooks/UX: `beforeInstallation(Closure)`, `afterInstallation(Closure)`, `silent()`, `copyAndRegisterServiceProviderInApp(?string $providerClass = null)`, `askToStarRepoOnGitHub(?string $repoUrl = null)`.

```php
$packager->hasInstallCommand(function (InstallCommand $command) {
    $command->publishConfig()
        ->publishMigrations()
        ->publishForLocal('routes')
        ->askToStarRepoOnGitHub('https://github.com/you/pkg');
});
```

## Publishing tags

Publish groups are tagged `"{shortName}{separator}{group}"`. The default separator is `::`, e.g. `my-package::config`. Consumers of *your* package publish with:

```bash
php artisan vendor:publish --tag="my-package::config"
```

To use the classic flat format (`my-package-config`), set the separator in `configure()`:

- `hasPublishTagSeparator(string|array $separator): static` — e.g. `->hasPublishTagSeparator('-')`.
- Pass an **array** to register every group under multiple tag forms at once: `->hasPublishTagSeparator(['::', '-'])` makes both `my-package::config` and `my-package-config` publish the same resource. The first separator is primary (used by the install command).

It applies to every publish tag and to the install command consistently. Groups: `config`, `migrations`, `routes`, `translations`, `assets`, `views`, `providers`, `view-components`, `view-component-namespaces`.

## Common gotchas

- Paths are relative to the **provider file's directory**; the default prefixes (`../config`, etc.) assume the provider is one level deep (e.g. `src/`). Adjust the `$directory` argument if your layout differs.
- Forgetting `name()` → `MissingNameException` at registration.
- A config file that doesn't `return []` → `InvalidReturnTypeException`.
- Migrations are **not** loaded at runtime unless you call `canLoadMigrations()`; otherwise they are publish-only.
- `configure()` must not return a value (`void`); it mutates `$packager`.
