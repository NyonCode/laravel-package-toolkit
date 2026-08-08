---
title: The Packager
description: Names, short names, base paths and the file resolver every hasX() method shares.
---

# The Packager

`Packager` is the object your `configure()` method receives. It carries the whole description of
your package: what it has, where those things live, and how they should be named once they reach a
consumer's application.

Every builder on it returns `static`, so configuration is one chain, and every builder validates
what it is given the moment it is given it.

## Name

```php
$packager->name('My Awesome Package');
```

Required. An empty or whitespace-only name throws `InvalidArgumentException` immediately; a name
that is never set throws `MissingNameException` when the provider validates its configuration.

The name is the human-facing label. It appears in `php artisan about`, in the install command's
description (`Install My Awesome Package package`) and in its welcome banner.

## Short name

The short name is the machine-facing identifier, and it is doing far more work than the name is.
It is the prefix of every publish tag, the namespace of your views and translations, the directory
your assets are mirrored into, and the prefix of your install command.

```php
$packager->name('My Awesome Package');

$packager->shortName(); // 'my-awesome-package'
```

It is derived with `Str::kebab()` the first time it is asked for, and cached. To set it yourself:

```php
$packager->name('My Awesome Package')->hasShortName('awesome');
```

`hasShortName()` validates twice over. The value must already equal `Str::kebab()` of itself, and
must match `/^[a-z0-9-]+$/` — lowercase letters, digits and hyphens only. Anything else throws
`InvalidArgumentException` with the offending value in the message.

```php
$packager->hasShortName('Awesome');     // ✗ not kebab-case
$packager->hasShortName('my_awesome');  // ✗ underscore
$packager->hasShortName('awesome-2');   // ✓
```

Here is what a short name of `blog` buys you, in full:

| | |
|---|---|
| Publish tags | `blog::config`, `blog::views`, `blog::migrations`, … |
| Views | `view('blog::post')` |
| Translations | `trans('blog::messages.title')` |
| Install command | `php artisan blog:install` |
| Published views | `resources/views/vendor/blog/` |
| Published translations | `lang/vendor/blog/` |
| Published routes | `routes/vendor/blog/` |
| Published stubs | `stubs/blog/` |
| Published assets | `public/vendor/blog/` |
| Optimize cache key | `blog` |

:::warning Changing the short name is a breaking change
Every one of the paths above moves with it, and a consumer who has published views or translations
will find their overrides silently ignored afterwards. Treat it the way you would treat a public
API rename.
:::

## Paths

Every relative path a `hasX()` method takes is resolved against the **base path**, which the
provider sets from its own location:

```php
$this->packager->hasBasePath($this->getPackageBaseDir());
```

`getPackageBaseDir()` reflects on your provider class, takes its filename, and returns the
directory. For the conventional layout — provider at `src/BlogServiceProvider.php` — the base path
is `src/`, which is why the defaults reach outwards with `../`:

```text
acme/blog/
├── config/          ← '../config'
├── database/
│   └── migrations/  ← '../database/migrations'
├── resources/views/ ← '../resources/views'
├── routes/          ← '../routes'
└── src/             ← the base path
    ├── BlogServiceProvider.php
    └── Commands/    ← 'Commands'   (no ../ — inside src)
```

### The `src/Providers` special case

If the base path ends in `src/Providers`, everything from `/Providers` onwards is trimmed off, so
the base path becomes `src` again:

```php
// src/Providers/BlogServiceProvider.php
// base path resolves to src/, not src/Providers/
```

Every default keeps working. This is the only path rewriting the toolkit does.

### Cross-platform normalisation

Paths are normalised before use: separators are converted to the host's `DIRECTORY_SEPARATOR`,
duplicate separators collapse, and a trailing separator is stripped. Both `'../config'` and
`'..\\config'` work on either platform, and the toolkit's own test suite runs on Windows and Linux
for exactly this reason.

## How files are resolved

Every file-based builder — `hasConfig()`, `hasRoutes()`, `hasMigrations()`, `hasSeeders()`,
`hasFactories()`, `hasStubs()`, `hasBroadcastChannels()`, `hasCommands()`, `hasProviders()` —
funnels into one resolver with the same two modes.

### Discovery mode

Pass nothing, and the directory is scanned:

```php
$packager->hasConfig();                 // every file in ../config
$packager->hasRoutes();                 // every file in ../routes
$packager->hasMigrations();             // every file in ../database/migrations
```

Discovery has three properties worth knowing:

- **It is not recursive.** Only files directly inside the directory are found. A
  `database/migrations/tenant/` subdirectory is invisible.
- **A missing directory is an error.** `DirectoryNotFoundException`, naming the resolved absolute
  path — so a typo in a custom `directory:` argument surfaces immediately.
- **Unreadable files are skipped**, not fatal. A file that cannot be read is left out of the set
  rather than taking the whole package down.

### Explicit mode

Pass a filename, or a list of them, and only those are used — in the order you list them:

```php
$packager->hasRoutes('api.php');
$packager->hasRoutes(['api.php', 'web.php']);
$packager->hasConfig(['blog.php', 'blog-cache.php']);
```

A name that does not resolve to a file throws `FileNotFoundException` with the resource type in the
message:

```text
Route file [admin.php] does not exist in directory [../routes].
```

Order matters more than it looks. Routes are loaded in the order given; migrations are published in
the order given, and for [timeless migrations](/migrations#timeless-migrations) that order decides
the generated timestamps, and therefore the order they run in.

### Relative and absolute paths

A name starting with `..`, `/` or a drive letter is treated as a path from the base path rather
than a filename inside the directory. This is how `hasProviders()` reaches into `../stubs`:

```php
$packager->hasProviders([
    '../stubs/BlogServiceProvider.stub',
    '../stubs/BlogEventServiceProvider.stub',
]);
```

### Custom directories

Every builder takes the directory as its second argument, so nothing forces you into the
conventional layout:

```php
$packager
    ->hasConfig(directory: '../resources/configuration')
    ->hasRoutes(directory: '../resources/routes')
    ->hasMigrations(directory: '../database/schema');
```

## Introspection

Each `hasX()` sets a flag, and each flag has a reader. The provider uses them to skip work; you can
use them in tests, or in your own `boot()` override.

| Reader | True after |
|---|---|
| `isConfigurable()` | `hasConfig()` found at least one file |
| `isRoutable()` | `hasRoutes()` found at least one file |
| `isBroadcastable()` | `hasBroadcastChannels()` found at least one file |
| `isMigratable()` | `hasMigrations()` found at least one file |
| `isSeedable()` | `hasSeeders()` found at least one file |
| `isFactorable()` | `hasFactories()` found at least one file |
| `isStubbable()` | `hasStubs()` found at least one file |
| `isTranslatable()` | `hasTranslations()` found a non-empty directory |
| `isViewable()` | `hasViews()` |
| `isViewComponentized()` | `hasComponents()` / `hasComponent()` |
| `isViewComponentNamespaceConfigured()` | `hasComponentNamespaces()` / `hasComponentNamespace()` |
| `isViewComposable()` | `hasViewComposer()` |
| `isSharedWithViews()` | `hasSharedDataForAllViews()` |
| `isAssetable()` | `hasAssets()` |
| `isCommandable()` | `hasCommands()` / `hasCommand()` |
| `isEventable()` | `hasEvents()` / `hasSubscribers()` |
| `isOptimizable()` | `hasOptimizeCommands()` |
| `isProvidable()` | `hasProviders()` / `hasProvider()` |
| `isInstallable()` | `hasInstallCommand()` or a preset |
| `isAboutable()` | `hasAbout()` |
| `isSetMiddlewareAliases()` | `hasMiddlewareAliases()` |
| `isSetMiddlewareGroups()` | `hasMiddlewareGroups()` |
| `isSetMiddlewareGlobals()` | `hasMiddlewareGlobals()` |

The matching getters return the resolved values: `configFiles()`, `routeFiles()`,
`migrationFiles()`, `seederFiles()`, `factoryFiles()`, `stubFiles()`,
`broadcastChannelFiles()` (all arrays of `Support\SplFileInfo`), plus `views()`,
`translationPath()`, `assetDirectory()`, `viewComponents()`, `viewComponentPaths()`,
`viewComponentNamespaces()`, `viewComposers()`, `viewSharedData()`, `events()`, `subscribers()`,
`optimizeCommands()`, `providers()` and `commands`.

## `Support\SplFileInfo`

File sets are returned as a thin subclass of PHP's `SplFileInfo` with one addition that the toolkit
leans on constantly:

```php
$file->getBasename();     // 'blog.php'      — as SplFileInfo
$file->getBaseFileName(); // 'blog'          — added: name without extension
$file->getPathname();     // absolute path
$file->getFileSize();     // alias of getSize()
```

`getBaseFileName()` is what makes `config/blog.php` merge under the config key `blog`, and what
lets a seeder shipped as `TestSeeder.stub` publish as `TestSeeder.php`.
