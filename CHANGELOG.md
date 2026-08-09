# Changelog

All notable changes to `laravel-package-toolkit` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/).

## [2.4.0] - 2026-08-07

### Added

- **`hasAssets(entries: [...])` and the asset directives** — name the files a template renders and the toolkit
  registers `@packageAssets`, `@packageStyles`, `@packageScripts` and `@packageAssetUrl`, each taking the package
  short name. This replaces the boilerplate the toolkit used to leave to the package author: a helper class holding the
  paths, a method assembling and escaping the markup, and a `Blade::directive()` registered from a lifecycle hook — all
  of it derived from facts the packager already had. Paths are validated at declaration, so a typo throws where it was
  written rather than 404ing in a browser. `.js` renders as `type="module"` with `data-navigate-track="reload"`;
  `Support\Asset::make('js/x.js')->classic()` opts a shipped IIFE or UMD bundle out, since a module is deferred and its
  top-level declarations never reach `window`. Directives are registered per container, guarded on the compiler's
  existing directives rather than a static, so a rebuilt application re-registers and a second package does not.
- **`hasViteAssets()`** — declare sources the **consuming application's** Vite build can compile. The toolkit still
  ships no Vite config, no build step and no manifest of its own: a package building its own would produce a second
  manifest, a second dev server and a second set of hashed filenames, and would still have to hand plain files to a
  template — which the mirror already does. What was missing is the other direction. An application on Tailwind must
  run its own config over the package's Blade markup or half the package's classes are purged, and an application
  bundling its own JavaScript would rather not ship a second copy of a shared dependency; both need the package's
  *sources* in the application's build, and then the package's own layout has to emit a hashed filename it can only
  learn from the application's manifest. Declaring `'resources/js/blog.js' => 'js/blog.js'` covers both: the entry
  resolves through the dev server while `npm run dev` runs, through the application's manifest once built, and through
  the shipped file otherwise — per entry, so an application can build the CSS and leave the JavaScript alone. A miss
  falls back instead of throwing, because a package's layout cannot fix the application's Vite config and a 500 on
  every page is a poor way to report a missed optimisation. The manifest key prefix is derived from the package's
  location under the application; `base:` states it for a symlinked path repository, where nothing can be derived.
- **`PackageAssets::resolution()`** — names how each entry resolves right now (`dev server`, `application build`,
  `shipped`, `not published`, `unresolved`), the counterpart to `PublishedAssets::isStale()` and there for the same
  reason. Falling back is silent by design, which means the most common mistake on the application's side is silent
  too: an input listed under a path one segment off from the manifest key leaves every page working, served from the
  shipped file, with nothing saying the configured build is unused. A package that called `hasAbout()` and declared
  Vite sources gets the same summary as an `Assets` line in `php artisan about`. Nothing is written to report —
  an entry the mirror would publish on demand reports `shipped` on the strength of the file existing.
- **CSP nonces on toolkit-rendered tags** — `Vite::useCspNonce()` nonces every tag Laravel generates, so without this
  the two halves of one declaration behave differently under a strict policy: the entry the application built loads
  and the one falling back to the shipped file is blocked. An entry's own `nonce` attribute wins.
- **`hasBroadcastChannels()`** — registers channel authorization files (`Broadcast::channel()`) with the broadcaster.
  Until now a channel file had to be smuggled through `hasRoutes()`, which loads it into the router inside a route
  group — the wrong destination for an authorization callback. Discovers the package's `routes` directory by default,
  or takes explicit files and a directory. Channels are load-only and deliberately not publishable: an application does
  not load `routes/channels.php` unless its own bootstrap asks for it, so a published copy would look authoritative
  while the package kept using its own. Skipped silently when `illuminate/broadcasting` is absent.
- **`hasSeeders()`** — publishes database seeders under `<package-short-name>::seeders`, flat into the application's
  `database/seeders` directory rather than a `vendor/<package>` subdirectory, because that is where the application's
  own `Database\Seeders` namespace resolves — a published seeder is immediately runnable with
  `db:seed --class=Database\Seeders\<Name>`. A seeder shipped as `.stub` is published as `.php`.
- **`hasFactories()`** — publishes model factories under `<package-short-name>::factories`, flat into
  `database/factories` for the same reason. Publishing is the only mechanism offered on purpose: Laravel removed
  `loadFactoriesFrom()` in version 8, so a package that wants unpublished factories used must point at them from its
  model's `newFactory()`.
- **`hasStubs()`** — publishes generator stubs under `<package-short-name>::stubs` to `stubs/<package-short-name>/`,
  keeping the original extension. The subdirectory is not decoration: `stubs/` is one flat directory shared with
  `php artisan stub:publish` and with every other installed package.
- **Install command** — `publishSeeders()`, `publishFactories()` and `publishStubs()`, all three included in
  `publishEverything()`, each with its own progress step. Note that `getInstallationSteps()` only runs tags it knows
  about, so a new publishable resource has to be listed there or the install command silently skips it.
- **`PublishedAssets::flush()`** — forgets the resolved URLs and the per-request sync marks, for a long-lived worker
  where the singleton outlives the request it was scoped to. Without it the mirror is attempted at most once per
  worker boot, so a published copy deleted underneath a running worker is never put back, and every URL keeps
  emitting the `?id=<mtime>` of the release the worker started on — the query string Livewire's
  `data-navigate-track` watches to notice a deploy. Call it from the framework's request-terminated hook. The
  directories declared by `hasAssets()` are kept: providers register those once per worker boot, not per request.
- **Agent support shipped with the package** — `ai/AGENTS.md` is the complete public API in one file, and it now
  travels inside the package rather than living only in this repository. That is the whole point: an agent adding a
  resource to someone's package otherwise reconstructs this API from whatever release was in its training data, and
  a copy pasted into their project goes stale at the next release, silently, in the one file nobody re-reads. A
  reference into `vendor/` cannot. `vendor/bin/package-toolkit-ai install` wires it into a consuming project three
  ways — a delimited block in the project's `AGENTS.md`, a Claude Code skill under `.claude/skills/`, and an MCP
  server registered in `.mcp.json` — each independent, each idempotent, and all three reversible with `remove`,
  which is what the markers and the namespaced JSON key are for.
- **MCP server (`ai/mcp/server.mjs`)** — `search_docs`, `list_docs`, `get_doc`, `list_api` and `describe_api`.
  The last two parse signatures and docblocks out of the installed `src/`, so a method's arguments are read rather
  than recalled; the first three serve the pages the documentation site publishes, so a page the site does not build
  is not offered as fact. Node 18+ and no dependencies — the MCP stdio transport is newline-delimited JSON-RPC,
  which is less code to implement than an SDK inside `vendor/` would be to install. `--self-test` parses everything
  and prints what it found.
- **`llms.txt` on the documentation site** — plus `llms-full.txt` and a `.md` twin of every page, generated from the
  same Markdown as the HTML so the two cannot disagree. Links inside them are absolute and point at other `.md`
  files, so an agent that follows one stays in Markdown; each HTML page advertises its twin as
  `<link rel="alternate" type="text/markdown">`.

## [2.3.0] - 2026-08-07

### Added

- **Asset mirror** — `Support\PublishedAssets`, a container singleton shared by every package, keeps
  `public/vendor/<package-short-name>` in step with the directory named by `hasAssets()` without anyone running a
  command. Ask it for a URL with `app(PublishedAssets::class)->url($shortName, $absolutePath)`: the first asset of a
  package to resolve one in a request compares each shipped file against its published counterpart and copies only
  what is missing or older, so in steady state it is a handful of `stat` calls and no writes. Copies land through a
  temporary file and `rename()`, so a concurrent request never sees a half-written file. The returned URL is
  cache-busted by the published copy's mtime, which is what keeps Livewire's `data-navigate-track` meaningful across
  an upgrade. Where `public/` cannot be written — a read-only container, Vapor — nothing throws: `url()` returns
  `null` and `isStale()` reports a copy left behind, so the caller can fall back and warn.
- **`hasAssets(string $directory = 'dist', bool $mirror = true)`** — the new second argument opts a package out of the
  mirror while keeping its publish tags.

### Changed

- **Assets also publish under `laravel-assets`** — in addition to `<package-short-name>::assets`. That tag is what the
  Laravel application skeleton already runs from composer's `post-update-cmd`
  (`vendor:publish --tag=laravel-assets --ansi --force`), the same hook Horizon, Telescope and Nova rely on, so one
  command covers every installed package. Laravel accumulates groups per path, so both tags publish the same files and
  an untagged `vendor:publish` is unaffected.

## [2.2.0] - 2026-07-20

### Added

- **Event registration** — `hasEvents()`, `hasEvent()`, `hasSubscribers()` and `hasSubscriber()` register event
  listeners and subscribers, applied at boot via the `Event` facade.
- **Optimize command registration** — `hasOptimizeCommands()` registers artisan commands that run with
  `php artisan optimize` and `php artisan optimize:clear`, forwarding to Laravel's `ServiceProvider::optimizes()`.
- **Configurable publish tag separator** — `hasPublishTagSeparator()` sets the separator used for publish tags.
  Pass `-` for the classic flat format (`my-package-config`) instead of the default `my-package::config`; applies to
  `vendor:publish` tags and the install command alike. Pass an array (e.g. `['::', '-']`) to register every group
  under multiple tag forms at once, with the first separator treated as primary.

## [2.1.1] - 2026-07-02

### Fixed

- **`hasAssets()` stored a wrong path** — the directory was validated as `../<dir>` but stored without the `../`
  prefix, so `vendor:publish --tag=<name>::assets` silently published nothing.
- **`hasTranslations()` rejected valid languages** — the language-directory check used `Collection::search()`,
  whose `0` index for the first supported language was treated as a failure. Region locales
  (e.g. `pt_BR`, `en-US`) are now accepted as well.
- **`hasViews()` broke with a custom path** — a relative path was passed unresolved to `loadViewsFrom()` and an
  absolute path (as documented) failed validation. Both are now resolved correctly.
- **`routes`, `view-components` and `view-component-namespaces` publish tags did nothing** — the corresponding
  publish registrations were missing or never called. Route files are published to
  `routes/vendor/<package-short-name>/`.
- **`publishMigrations()` ignored an explicit file selection** — publishing the whole directory of the first
  migration file instead of the configured files.
- **View components with list-style arrays received numeric aliases** — a component at index 1+ of a
  non-associative array was registered under the alias `1`; `hasComponent()` also registered the component twice.
- **`packageCommands()` was never called** — commands returned from the documented override are now registered.
- **Install command no longer calls `exit(0)`** — cancelling the production confirmation returns a proper exit
  code instead of terminating the process.
- **`getVersion()` no longer throws** for packages without a composer `name` or not installed via Composer.
- Test suite: `PackageConfigWithFileNames` was missing the `Test` suffix and never ran.

### Changed

- `bootVewComposers()` was renamed to `bootViewComposers()`; the misspelled method is kept as a deprecated alias.
- PHPStan configuration added (`phpstan.neon`, level 5) so `composer lint` works.
- **Install command respects the provider's `$tagSeparator`** — the tag separator is passed to the install
  command instead of being hardcoded to `::`.
- **`Packable` is now part of the contract hierarchy** — `ProvidesPackageServices` extends `Packable`, so
  providers following the documented `implements Packable` pattern are covered by the toolkit itself.

### Deprecated

- The `HasAbout` contract — unused by the toolkit; `Packable` already declares `aboutData()`. Will be removed
  in 3.0.

## [2.1.0] - 2026-06-30

### Removed

- **Dropped Laravel 10.x and 11.x support** — both branches have passed their security-support end-of-life. The
  June 2026 advisories (including a High-severity CRLF injection, CVE-2026-48019) were patched only in Laravel
  12.60+/13.9+ and never backported to 10.x or 11.x, so no secure release exists on those branches.

### Changed

- **Minimum Laravel version raised to 12.61.1 / 13.12.0** — the `illuminate/support` constraint is now
  `^12.61.1|^13.12.0`, pinning to the first releases patched against the June 2026 advisories. If you still depend on
  Laravel 10 or 11, stay on the `~2.0.0` release.

## [2.0.2] - 2026-06-30

### Fixed

- **About command data not merging** — Custom about data returned from `aboutData()`
  on a service provider was never included in the `php artisan about` output; only the
  package `Version` was displayed. The provider's `aboutData()` is now passed through to
  the packager and merged with the version data as documented.
