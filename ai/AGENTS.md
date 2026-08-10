# Using laravel-package-toolkit (agent reference)

Audience: an AI agent working **in a project that builds a Laravel package** with
`nyoncode/laravel-package-toolkit`. This is the complete public API, extracted from source.
Everything is a fluent builder — every `Packager` method returns `static`, so chain freely.

This file ships inside the package, so it is always the version that matches the installed
release: `vendor/nyoncode/laravel-package-toolkit/ai/AGENTS.md`. Run
`vendor/bin/package-toolkit-ai install` to wire it into the project's own `AGENTS.md`, install the
Claude Code skill, and register the MCP server — see [Tooling for agents](#tooling-for-agents) at
the foot of this file.

Requires PHP `^8.2` and Laravel `12.x` (>= 12.61.1) or `13.x` (>= 13.12.0).

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

## Seeders

- `hasSeeders(array|string|null $seederFiles = null, string $directory = '../database/seeders'): static`
- `null` → all files in `../database/seeders`; or pass specific filenames.
- **Publish-only** — nothing is loaded at boot. Published **flat** into the app's `database/seeders`, not into a `vendor/{shortName}` subdirectory, so the app's own `Database\Seeders` namespace resolves the file and `php artisan db:seed --class=Database\Seeders\MySeeder` works straight away.
- A seeder may ship as a `.stub`; it is published as `.php` (same convention as `hasProvider()`).

## Factories

- `hasFactories(array|string|null $factoryFiles = null, string $directory = '../database/factories'): static`
- Publish-only, flat into `database/factories` for the same namespace reason.
- Laravel dropped `loadFactoriesFrom()` in v8, so there is no way to make a package's *unpublished* factories discoverable from the toolkit. If you need that, give the model a `newFactory()` returning your factory class.

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

- `hasAssets(string $directory = 'dist', bool $mirror = true, array $entries = []): static` — mark a directory of built assets as publishable, and name the ones a template renders.
- Published to `public/vendor/{shortName}` under **two** tags: `{shortName}::assets` and Laravel's conventional `laravel-assets` (what the app skeleton's `post-update-cmd` runs).
- With `$mirror` left on, the provider registers the directory with the shared `Support\PublishedAssets` container singleton, which lazily copies missing/outdated files into `public/vendor/{shortName}` the first time an asset resolves a URL in a request. Ask it for one with `app(PublishedAssets::class)->url($shortName, $absolutePath)` — it returns an mtime-cache-busted URL, or `null` when `public/` is unwritable and nothing was published before (`isStale()` reports that case). Pass `mirror: false` to keep the publish tags but skip the mirror.
- **Do not build a tag around it in a template.** `<script src="{{ app(PublishedAssets::class)->url(...) }}">` is the pre-2.4.0 pattern and is wrong now: `url()` is nullable, so an unwritable `public/` renders `src=""` — a browser resolves that against the current page and fetches its HTML as a script, with no throw and no 404 — and the tag carries no `type="module"`, no `data-navigate-track`, no CSP nonce and no Vite resolution. Declare the file in `entries:` and render `@packageScripts` / `@packageStyles`, or take the bare URL from `@packageAssetUrl` / `app(PackageAssets::class)->url($shortName, $entryKey)`, which keys off the entry rather than an absolute path. `PublishedAssets` direct is for what is *not* a declared entry — an image or font the template composes itself, an application-registered asset — and for `isStale()`.
- **Omitting `entries:` discovers them** (2.4.1): the stylesheets and scripts directly inside the asset directory and its `css/` and `js/` subdirectories become entries, sorted alphabetically. It follows whatever directory `hasAssets()` was given, not `dist` literally. Extensions are an allowlist (`css`, `scss`, `sass`, `less`, `styl`, `pcss`, `js`, `mjs`, `cjs`), so maps/fonts/images/`manifest.json` are skipped, and it is **not** recursive — a code-split build's chunk directory (Vite's `assets/`) is deliberately left alone, because a chunk is imported by an entry point rather than loaded beside it. Naming any entry replaces discovery outright; the two do not merge. Name them explicitly for a code-split build, for an IIFE bundle (a discovered script is always a module), or to avoid the directory listing that runs once per boot.
- `entries:` are paths inside the asset directory, validated at declaration. Declaring any registers four global Blade directives taking the package short name: `@packageAssets($package, ...$only)`, `@packageStyles(…)`, `@packageScripts(…)` and `@packageAssetUrl($package, $entry)` (URL only). `.js` renders as `type="module"` with `data-navigate-track="reload"`; pass `Support\Asset::make('js/x.js')->classic()` for an IIFE/UMD bundle, or `->attributes([...])` / `->asStylesheet()` for the rest.
- **The short name is optional since 2.4.2** on the three tag directives: `@packageAssets` / `@packageStyles` / `@packageScripts` with no argument render every package that declared entries, in provider-boot order, with stylesheets leading across the whole set rather than per package — within each of the two halves, that is: what the application built is emitted as one Vite block (preloads, stylesheets, scripts, in Vite's order) and that block leads, so a built script precedes a stylesheet that fell back to the shipped copy. Prefer it in an application layout — a layout that names its packages has to be edited whenever one is installed or removed, and `package:discover` does not help (it discovers providers, not template lines). `@packageAssetUrl` still takes both arguments; there is no URL of every package.
- `hasAssetFallback(Closure $resolver): static` (2.4.2) — where to serve a shipped file from when nothing is published, `fn (string $file, string $package): ?string`. Without it, an entry that resolves to nothing renders **no tag at all**, which is right for an entry the application declined to build and wrong for the one that is the package's only copy: an unwritable `public/` (read-only container, Vapor, shared hosting) then costs a page its stylesheet or its behaviour, silently. A package serving its assets from a route of its own points at it here and keeps the tag *with* `type="module"`/`classic()`+`defer`, its attributes, `data-navigate-track` and the CSP nonce — which is the whole reason to declare it rather than hand-write a `<script>` beside the directive. Reached only after the mirror and `public/vendor/{shortName}` both came back empty, so a normal deployment never calls it; the resolver owns the entire URL including any cache-buster, and returning `null` drops the tag as before. Throws `PackageConfigurationException` if declared before `hasAssets()`.
- `hasViteAssets(array $entries, ?string $base = null): static` — declare sources the **consuming application's** Vite build can compile (`'resources/js/blog.js' => 'js/blog.js'` maps a source to the shipped file it stands in for; a plain list declares sources with no shipped copy). The toolkit builds nothing and ships no Vite config; this only makes the application's build a first-class way to serve the package. Each entry resolves per request: dev server while `npm run dev` runs → the application's manifest key `{base}/{source}` → the shipped file via the mirror. `$base` is derived from the package's location under `base_path()` (`vendor/acme/blog`); pass it explicitly for a symlinked path repository, where nothing can be derived. A miss falls back silently rather than throwing.
- Declaring the same shipped file in both calls is intended, not a duplicate: the later entry replaces the earlier one and renders once, in the position first declared. Since 2.4.1 the replacement **inherits the earlier entry's presentation** — `classic()` is sticky, attributes merge with the newer winning a collision, and an explicit `asStylesheet()`/`asScript()` on the replacement stands. So `Asset::make('js/blog.js')->classic()` plus the `hasViteAssets()` shorthand for the same file keeps the shipped bundle classic on the fallback path; before 2.4.1 it silently became a module and the IIFE's globals stopped reaching `window`.
- Diagnostics: falling back is silent by design, so `app(PackageAssets::class)->resolution($shortName)` names how each entry resolves right now (`dev server` / `application build` / `shipped` / `fallback` / `not published` / `unresolved`) without writing anything. A package that called `hasAbout()` and declared Vite sources also gets an `Assets` line in its `php artisan about` section. Use it when an application swears it added the input but the shipped file is still being served. `shipped` for a mirrored entry means the published copy exists *or* the mirror could still make it — since 2.4.2 that second half is checked (nearest existing ancestor of `public/vendor/{shortName}` is writable) rather than assumed, so an unwritable `public/` reports `fallback` or `not published` instead of a healthy-looking `shipped`. A fresh install, where nothing has been published yet because the mirror is lazy, still reports `shipped`.
- CSP: `Vite::useCspNonce()` nonces are carried onto the tags the toolkit renders itself, so the shipped-file path is not blocked while the application-built path loads. An entry's own `nonce` attribute wins.
- Under a long-lived worker the singleton outlives the request it was scoped to, so call `app(PublishedAssets::class)->flush()` from the framework's request-terminated hook — otherwise the memo survives a deploy: the mirror runs at most once per worker boot, and every URL keeps the `?id=` of the release the worker started on. `flush()` keeps the declared directories; it only forgets the resolved URLs and the sync marks.

## Stubs

- `hasStubs(array|string|null $stubFiles = null, string $directory = '../stubs'): static`
- Publish-only, published to `stubs/{shortName}/` with the original extension preserved (`command.stub` stays `.stub`).
- The short-name subdirectory keeps them clear of `php artisan stub:publish` output and of other packages, which all share the flat `stubs/` directory.

## Providers (extra service providers)

- `hasProvider(string $provider): static`
- `hasProviders(array $providers): static`
- Used with the install command to copy/register additional providers into the consuming app.

## Broadcast channels

- `hasBroadcastChannels(array|string|null $channelFiles = null, string $directory = '../routes'): static`
- `null` → all files in `../routes`; or pass specific filenames (`['channels.php']`).
- Each file is `require`d at boot so its `Broadcast::channel()` calls register with the broadcaster. Do **not** route channel files through `hasRoutes()` — that loads them into the router inside a route group.
- **Not publishable by design.** An app does not load `routes/channels.php` unless its own bootstrap asks for it, so a published copy would look authoritative while your package kept using its own. A consumer overrides authorization by re-registering the same channel name from their app; the last registration wins.
- Silently skipped when `illuminate/broadcasting` is not installed.

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
- Per-resource: `publishConfig()`, `publishMigrations()`, `publishSeeders()`, `publishFactories()`, `publishRoutes()`, `publishViews()`, `publishAssets()`, `publishTranslations()`, `publishProviders()`, `publishStubs()`, `publishComponents()`, `publishComponentNamespaces()` (plus verbose aliases like `publishConfigFiles()`, `publishLanguageFiles()`, `publishServiceProviders()`…).
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

It applies to every publish tag and to the install command consistently. Groups: `config`, `migrations`, `seeders`, `factories`, `routes`, `translations`, `assets`, `views`, `providers`, `stubs`, `view-components`, `view-component-namespaces`. Broadcast channels have no group — they are load-only.

## Common gotchas

- Paths are relative to the **provider file's directory**; the default prefixes (`../config`, etc.) assume the provider is one level deep (e.g. `src/`). Adjust the `$directory` argument if your layout differs.
- Forgetting `name()` → `MissingNameException` at registration.
- A config file that doesn't `return []` → `InvalidReturnTypeException`.
- Migrations are **not** loaded at runtime unless you call `canLoadMigrations()`; otherwise they are publish-only.
- Seeders, factories, stubs and extra providers are publish-only with no runtime equivalent; broadcast channels are the opposite — load-only, never published.
- `configure()` must not return a value (`void`); it mutates `$packager`.

## Before you call the work done

Every one of these is cheap and each one catches a class of failure the others do not.

1. **The declaration matches the directory.** A `hasX()` pointing at a directory that does not exist
   throws at registration for most resources — but an *empty* directory does not. Check that the
   files you expect are actually there.
2. **The package still boots.** `php artisan about` (with `hasAbout()`) or any artisan call inside
   the consuming app exercises register + boot. A silent app is a booting app.
3. **The publish tags exist.** `php artisan vendor:publish --list` shows every tag the provider
   registered. A resource you declared that is missing from that list was declared but not published
   — check whether it is a publish-only, load-only or both resource in the table above.
4. **The install command really publishes.** `php artisan <short-name>:install --no-interaction` on
   a scratch app, then look at what landed. A tag the install command does not know about is dropped
   without a word.
5. **Nothing in `configure()` closes over unserialisable state** if the app will run
   `config:cache` / `route:cache`.

## Tooling for agents

Three optional pieces, all shipped in `vendor/nyoncode/laravel-package-toolkit/ai/`:

| What | Where | Install |
|---|---|---|
| This guide, referenced from the project's own `AGENTS.md` | `ai/AGENTS.md` | `vendor/bin/package-toolkit-ai install` |
| Claude Code skill (`/laravel-package-toolkit`) | `ai/skills/laravel-package-toolkit/SKILL.md` | same command, copies into `.claude/skills/` |
| MCP server — searches these docs and the toolkit source | `ai/mcp/server.mjs` | same command, writes `.mcp.json` |

The MCP server needs Node 18+ and has no dependencies. It exposes five tools: `list_docs`,
`get_doc`, `search_docs`, `list_api` and `describe_api` — the last two parse signatures and
docblocks straight out of `src/`, so they answer from the installed release rather than from
training data.

Without any of that, the documentation is also published in AI-readable form at
<https://package-toolkit.nyoncode.cz/llms.txt> (index) and
<https://package-toolkit.nyoncode.cz/llms-full.txt> (every page, one file), and
every documentation page has a raw Markdown twin at `<page-url>.md`.
