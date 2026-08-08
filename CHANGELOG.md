# Changelog

All notable changes to `laravel-package-toolkit` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/).

## [2.4.0] - 2026-08-07

### Added

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
