---
title: API reference
description: Every public method on Packager, PackageServiceProvider, InstallCommand and the support classes, on one page.
---

# API reference

Every public method the toolkit exposes, grouped by the class it lives on. Types are as declared in
source; `static` means the method returns `$this` for chaining.

## `Packager`

The object passed to `configure()`.

### Identity

| Method | Returns | Notes |
|---|---|---|
| `name(string $name)` | `static` | Required. Throws `InvalidArgumentException` when empty |
| `shortName()` | `string` | `Str::kebab($name)`, derived lazily and cached |
| `hasShortName(string $shortName)` | `static` | Must be kebab-case and match `/^[a-z0-9-]+$/` |
| `basePath()` | `string` | Directory every relative path resolves against |
| `hasBasePath(string $basePath)` | `string` | Called by the provider; trims a `src/Providers` suffix |
| `path(string $path)` | `string` | Absolute path for a package-relative one |
| `resolveFiles(string\|array\|null $files, string $directory = '', string $type = '')` | `SplFileInfo[]` | The shared resolver behind every file-based builder |

### Config

| Method | Returns |
|---|---|
| `hasConfig(string\|array\|null $configFiles = null, string $directory = '../config')` | `static` |
| `isConfigurable()` | `bool` |
| `configFiles()` | `SplFileInfo[]` |

### Routes and channels

| Method | Returns |
|---|---|
| `hasRoutes(array\|string\|null $routeFiles = null, string $directory = '../routes')` | `static` |
| `isRoutable()` | `bool` |
| `routeFiles()` | `SplFileInfo[]` |
| `hasBroadcastChannels(array\|string\|null $channelFiles = null, string $directory = '../routes')` | `static` |
| `isBroadcastable()` | `bool` |
| `broadcastChannelFiles()` | `SplFileInfo[]` |

### Database

| Method | Returns |
|---|---|
| `hasMigrations(?array $migrationFiles = null, string $directory = '../database/migrations')` | `static` |
| `canLoadMigrations(bool $value = true)` | `static` |
| `isMigratable()` | `bool` |
| `migrationFiles()` | `SplFileInfo[]` |
| `hasDatePrefix(string $filename)` | `bool` |
| `shouldPrependTimestamp()` | `bool` |
| `getMigrationPublishMapping()` | `array<string, string>` |
| `hasSeeders(array\|string\|null $seederFiles = null, string $directory = '../database/seeders')` | `static` |
| `isSeedable()` | `bool` |
| `seederFiles()` | `SplFileInfo[]` |
| `hasFactories(array\|string\|null $factoryFiles = null, string $directory = '../database/factories')` | `static` |
| `isFactorable()` | `bool` |
| `factoryFiles()` | `SplFileInfo[]` |

`hasMigrations()` accepts an array only — not a bare string.
`$packager->hasMigrationsOnRun` is a public `bool` property set by `canLoadMigrations()`.

### Views

| Method | Returns |
|---|---|
| `hasViews(?string $viewsPath = null, string $directory = '../resources/views', ?string $namespace = null)` | `static` |
| `isViewable()` | `bool` |
| `views()` | `string` |
| `hasComponent(string $prefix, string $componentClass, string $alias = '')` | `static` |
| `hasComponents(string $prefix, array\|string $components)` | `static` |
| `isViewComponentized()` | `bool` |
| `viewComponents()` | `array` |
| `viewComponentPaths()` | `array` |
| `hasComponentNamespace(string $prefix, string $namespace)` | `static` |
| `hasComponentNamespaces(array $namespaces)` | `static` |
| `isViewComponentNamespaceConfigured()` | `bool` |
| `viewComponentNamespaces()` | `array<string, string>` |
| `hasViewComposer(string\|array $views, string\|Closure $composer)` | `static` |
| `isViewComposable()` | `bool` |
| `viewComposers()` | `array` |
| `hasSharedDataForAllViews(array $viewSharedData)` | `static` |
| `isSharedWithViews()` | `bool` |
| `viewSharedData()` | `array` |

The `$namespace` argument of `hasViews()` is [not currently applied](/views#the-namespace-parameter).

### Translations

| Method | Returns |
|---|---|
| `hasTranslations(string $translationPath = 'lang')` | `static` |
| `isTranslatable()` | `bool` |
| `translationPath()` | `string` |
| `loadJsonTranslate()` | `bool` |

### Assets

| Method | Returns |
|---|---|
| `hasAssets(string $directory = 'dist', bool $mirror = true)` | `static` |
| `isAssetable()` | `bool` |
| `assetDirectory()` | `string` |
| `mirrorsAssets()` | `bool` |

### Middleware

| Method | Returns |
|---|---|
| `hasMiddlewareAliases(array $aliases)` | `static` |
| `hasMiddlewareGroups(array $groups)` | `static` |
| `hasMiddlewareGlobals(array $middlewares)` | `static` |
| `getMiddlewareAliases()` | `array` |
| `getMiddlewareGroups()` | `array` |
| `getMiddlewareGlobals()` | `array` |
| `isSetMiddlewareAliases()` | `bool` |
| `isSetMiddlewareGroups()` | `bool` |
| `isSetMiddlewareGlobals()` | `bool` |

### Events

| Method | Returns |
|---|---|
| `hasEvent(string $event, string\|Closure\|array $listeners)` | `static` |
| `hasEvents(array $events)` | `static` |
| `hasSubscriber(string $subscriber)` | `static` |
| `hasSubscribers(array $subscribers)` | `static` |
| `isEventable()` | `bool` |
| `events()` | `array<string, array>` |
| `subscribers()` | `array<int, string>` |

### Commands and optimize

| Method | Returns |
|---|---|
| `hasCommand(string $commandClass)` | `static` |
| `hasCommands(string\|array\|null $commandsClass = null, string $directory = 'Commands')` | `static` |
| `isCommandable()` | `bool` |
| `hasOptimizeCommands(?string $optimize = null, ?string $clear = null, ?string $key = null)` | `static` |
| `isOptimizable()` | `bool` |
| `optimizeCommands()` | `array` |

`$packager->commands` is a public `string[]` property.

### Stubs and providers

| Method | Returns |
|---|---|
| `hasStubs(array\|string\|null $stubFiles = null, string $directory = '../stubs')` | `static` |
| `isStubbable()` | `bool` |
| `stubFiles()` | `SplFileInfo[]` |
| `hasProvider(string $provider)` | `static` |
| `hasProviders(array $providers)` | `static` |
| `isProvidable()` | `bool` |
| `providers()` | `SplFileInfo[]` |

### Installation

| Method | Returns |
|---|---|
| `hasInstallCommand(?Closure $callback = null)` | `static` |
| `withoutInstallCommand()` | `static` |
| `installCommandName(string $name)` | `static` |
| `installCommandHidden(bool $hidden = true)` | `static` |
| `installOnRun(bool $installOnRun = true)` | `static` |
| `installOnRunInEnvironment(string\|array $environments)` | `static` |
| `installOnRunInLocal()` | `static` |
| `installOnRunInProduction()` | `static` |
| `hasQuickInstall()` | `static` |
| `hasMinimalInstall()` | `static` |
| `hasFullInstall()` | `static` |
| `hasDevInstall()` | `static` |
| `isInstallable()` | `bool` |
| `isInstallCommandHidden()` | `bool` |
| `shouldInstallOnRun()` | `bool` |
| `getInstallCommandName()` | `string` |
| `getInstallCommandCallback()` | `?Closure` |
| `createInstallCommand()` | `InstallCommand` |

### About

| Method | Returns |
|---|---|
| `hasAbout(bool $value = true)` | `static` |
| `hasVersion(string $version)` | `static` |
| `setAboutData(array $data)` | `static` |
| `aboutData()` | `array` |
| `getVersion()` | `?string` |
| `isAboutable()` | `bool` |
| `bootAboutCommand()` | `void` |

`$packager->version` is a public `string` property.

### Publish tags

| Method | Returns |
|---|---|
| `hasPublishTagSeparator(string\|array $separator)` | `static` |
| `publishTagSeparator()` | `?string` — the primary one |
| `publishTagSeparators()` | `array<int, string>` |

### Lifecycle

| Method | Returns |
|---|---|
| `registeringPackage(Closure $callback)` | `static` |
| `registeredPackage(Closure $callback)` | `static` |
| `bootingPackage(Closure $callback)` | `static` |
| `bootedPackage(Closure $callback)` | `static` |
| `executeLifecycleHook(LifecycleHook $hook)` | `void` |

Public `bool` properties `registeringDefined`, `registeredDefined`, `bootingDefined` and
`bootedDefined` report whether each is set.

### Conditional configuration

| Method | Returns |
|---|---|
| `when(bool $condition, Closure $callback)` | `static` |
| `unless(bool $condition, Closure $callback)` | `static` |
| `whenMultiple(array $conditions)` | `static` |
| `whenEnvironment(string\|array $environments, Closure $callback)` | `static` |
| `whenProduction(Closure $callback)` | `static` |
| `whenLocal(Closure $callback)` | `static` — matches `local` **and** `development` |
| `whenConsole(Closure $callback)` | `static` |
| `whenClassExists(string $class, Closure $callback)` | `static` |
| `whenFunctionExists(string $function, Closure $callback)` | `static` |
| `whenExtensionLoaded(string $extension, Closure $callback)` | `static` |
| `executeConditionalCallbacks()` | `static` |
| `resetConditionalCallbacks()` | `static` |
| `conditionalCallbacksExecuted()` | `bool` |
| `getPendingConditionalCallbacksCount()` | `int` |

## `PackageServiceProvider`

| Method | Returns | Notes |
|---|---|---|
| `configure(Packager $packager)` | `void` | **abstract** — you implement it |
| `register()` | `void` | call `parent::register()` when overriding |
| `boot()` | `void` | call `parent::boot()` when overriding |
| `bootPackager()` | `Packager` | override to return a subclass |
| `getPackageBaseDir()` | `string` | reflection on `static::class` |
| `packageCommands()` | `array` | extra commands, merged with `hasCommands()` |
| `aboutData()` | `array` | extra `about` rows |
| `registeringPackage()` | `void` | fires the hook |
| `registeredPackage()` | `void` | fires the hook |
| `bootingPackage()` | `void` | fires the hook |
| `bootedPackage()` | `void` | fires the hook |
| `tagSeparator()` | `string` | primary separator |
| `tagSeparators()` | `array<int, string>` | all of them |
| `publishTagFormat(string $groupName)` | `string\|array` | the tag(s) for a group |

### Boot steps

Each returns `static`, so an override can chain: `bootAboutCommand()`, `bootMigrations()`,
`bootRoutes()`, `bootBroadcastChannels()`, `bootMiddleware()`, `bootEvents()`, `bootOptimizes()`,
`bootSharedViewData()`, `bootTranslations()`, `bootViewComposers()`,
`bootViewComponentNamespaces()`, `bootViewComponents()`, `bootViews()`, plus the umbrella
`bootPackageResources()`.

`bootVewComposers()` is a deprecated misspelling kept for backward compatibility.

### Publish steps

`publishAssets()`, `publishConfig()`, `publishFactories()`, `publishMigrations()`,
`publishProvider()`, `publishRoutes()`, `publishSeeders()`, `publishStubs()`,
`publishTranslations()`, `publishViewComponentNamespaces()`, `publishViewComponents()`,
`publishViews()`, plus the umbrella `registerPublishing()`.

## `Commands\InstallCommand`

### Publishing

`publishConfig()` · `publishConfigFile()` · `publishConfigFiles()` · `publishMigrations()` ·
`publishSeeders()` · `publishFactories()` · `publishAssets()` · `publishPublicAssets()` ·
`publishTranslations()` · `publishTranslationFiles()` · `publishLanguageFiles()` ·
`publishViews()` · `publishViewFiles()` · `publishRoutes()` · `publishRouteFiles()` ·
`publishProviders()` · `publishServiceProviders()` · `publishStubs()` · `publishComponents()` ·
`publishViewComponents()` · `publishComponentNamespaces()` · `publishViewComponentNamespaces()`

Bulk: `publishEverything()` · `publishAll()` · `publishEssentials()` · `publishCustom(...$tags)`

Conditional: `publishIf(bool, ...$tags)` · `publishUnless(bool, ...$tags)` ·
`publishForEnvironment(string|array, ...$tags)` · `publishForLocal(...$tags)` ·
`publishForProduction(...$tags)`

Inspection: `getPublishTags(): array` · `willPublish(string $tag): bool` ·
`clearPublishTags(): static`

### Behaviour

| Method | Notes |
|---|---|
| `beforeInstallation(Closure $callback)` | runs before publishing |
| `afterInstallation(Closure $callback)` | runs after publishing |
| `silent()` | suppress hook-phase output |
| `setTagSeparator(string $separator)` | set by the provider from the primary separator |
| `askToStarRepoOnGitHub(?string $repoUrl = null)` | falls back to `composer.json` |
| `copyAndRegisterServiceProviderInApp(?string $providerClass = null)` | `config/app.php` only |

## `Support\PublishedAssets`

| Method | Returns | Notes |
|---|---|---|
| `mirrors(string $package, string $assetDirectory)` | `void` | called by the provider |
| `url(string $package, string $path)` | `?string` | syncs, then returns a cache-busted URL |
| `isStale(string $package, string $path)` | `bool` | published copy older than the shipped one |
| `flush()` | `void` | clears URLs and sync marks, **keeps** directories |

## `Support\SplFileInfo`

Extends PHP's `SplFileInfo`, adding `getBaseFileName(): string` (name without extension) and
`getFileSize(): int`.

## `Support\Enums\Language`

A backed enum of ISO 639-1 codes — case name is the code, value is the English name.

| Method | Returns |
|---|---|
| `Language::codes()` | `Collection<int, string>` |
| `Language::names()` | `Collection<int, string>` |
| `Language::collection()` | `Collection<int, Language>` |

## `Support\Enums\LifecycleHook`

`Booting` · `Booted` · `Registering` · `Registered`

## Contracts

| Interface | Declares |
|---|---|
| `Contracts\Packable` | `configure()`, four lifecycle methods, `aboutData()` |
| `Contracts\ProvidesPackageServices` | extends `Packable`; adds `register()`, `boot()`, `packageCommands()` |
| `Contracts\HasAbout` | deprecated, removed in 3.0 |

## Exceptions

| Exception | Namespace | Thrown when |
|---|---|---|
| `MissingNameException` | `Exceptions\` | `name()` was never called |
| `InvalidReturnTypeException` | `Exceptions\` | a config file does not return an array |
| `InvalidLanguageDirectoryException` | `Exceptions\` | a translation subdirectory is not a language code |
| `PackageConfigurationException` | root namespace | the packager is `null`, or reflection fails |

The toolkit also lets `InvalidArgumentException`,
`Illuminate\Contracts\Filesystem\FileNotFoundException` and
`Symfony\Component\Finder\Exception\DirectoryNotFoundException` propagate from validation.
