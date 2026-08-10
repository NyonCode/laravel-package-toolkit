---
title: Upgrade guide
description: What changed in each release, what breaks, and what to do about it.
---

# Upgrade guide

The toolkit follows [Semantic Versioning](https://semver.org/), and the promise worth holding it to
is the one below the top number: nothing but a major release breaks working code. Minor releases add
functionality. A patch release occasionally does too, where the addition closes a gap rather than
opening a new surface — 2.4.1's asset discovery gave `hasAssets()` the file discovery every other
`hasX()` already had, and a package that named its entries never notices. Major releases may break.

| Constraint | Gets you |
|---|---|
| `^2.4` | the current line, including seeders, factories, stubs and broadcast channels |
| `^2.0` | Laravel 12/13, PHP 8.2+ |
| `~2.0.0` | the last line supporting Laravel 10 and 11 |
| `^1.0` | Laravel 9 |

:::warning 2.3.0 has been withdrawn
It is no longer available to install, and `^2.3` now resolves to 2.4 or later. Everything 2.3.0
introduced — the asset mirror and the `laravel-assets` publish tag — is in 2.4 unchanged, so
**the minimum supported version is `^2.4`**. If a lock file still pins 2.3.0,
`composer update nyoncode/laravel-package-toolkit` is the whole migration.
:::

## To 2.4.2

Nothing to do. Two additions and one correction, none of which changes what a package already
renders.

[**The three tag directives take no package name.**](/assets#naming-no-package-renders-every-one)
`@packageAssets`, `@packageStyles` and `@packageScripts` with no argument render every package that
declared entries. `@packageAssets('blog')` renders byte for byte what it did, so this is only worth
adopting where the layout is the application's own — that is the line that otherwise has to be
edited every time a package is installed or removed. `@packageAssetUrl` still takes both arguments.

[**`hasAssetFallback()`**](/assets#keeping-the-tag-hasassetfallback) says where to serve a shipped
file from when nothing is published. Worth declaring if your package already serves its assets from
a route of its own and you support deployments where `public/` cannot be written — a read-only
container, Vapor, shared hosting. There, an entry used to render no tag at all, and a page lost its
stylesheet or its behaviour with nothing to say why. Every other package is unaffected: the
resolver is reached only after both the mirror and `public/vendor/{short-name}` came back empty.

**`resolution()` stops reporting `shipped` for a copy that can never be written.** A mirrored
package — the default — claimed `shipped` for every entry outright, so an unwritable `public/`
looked healthy in `php artisan about` while the page rendered nothing. It now says `fallback`, or
`not published` where there is no fallback either. Nothing else moves: an entry the lazy mirror
simply has not reached yet, which is every entry on a fresh install, still reports `shipped`.

## To 2.4.1

Nothing to do. One thing starts working that previously did nothing:
[`hasAssets()` with no entries named](/assets#naming-nothing-discovers-them) now discovers them,
instead of leaving `@packageAssets` with nothing to render.

A package that already names its entries is untouched — naming any entry replaces discovery
outright. A package that called `hasAssets()` bare gets the stylesheets and scripts from the asset
directory's root and its `css/` and `js/` subdirectories, which is what a template asking for
`@packageAssets` wanted in the first place. The two cases discovery cannot read off a directory
listing still need naming: a **code-split build**, whose chunk directory is deliberately skipped,
and an **IIFE or UMD bundle**, since a discovered script is emitted as a module.

## To 2.4

Purely additive. Update the constraint and run `composer update`:

```json title="composer.json"
{
    "require": {
        "nyoncode/laravel-package-toolkit": "^2.4" // [tl! ++]
    }
}
```

### What is new

**[`hasBroadcastChannels()`](/broadcast-channels)** registers channel authorization files with the
broadcaster. If you were passing a channel file to `hasRoutes()` — the only option before — move it:

```php
$packager
    ->hasRoutes()                                  // [tl! --]
    ->hasRoutes(['web.php', 'api.php'])            // [tl! ++]
    ->hasBroadcastChannels(['channels.php']);      // [tl! ++]
```

The old arrangement kept working, but it loaded the authorization callbacks inside a route group and
put your channel names into the router. Both defaults point at `../routes`, so name the files
explicitly or move channels to their own directory.

**[`hasSeeders()`](/seeders)**, **[`hasFactories()`](/factories)** and **[`hasStubs()`](/stubs)**
add three publish-only resources, with the tags `blog::seeders`, `blog::factories` and
`blog::stubs`.

**The install command** gained `publishSeeders()`, `publishFactories()` and `publishStubs()`, all
three included in `publishEverything()`.

**[`PublishedAssets::flush()`](/assets#flush-for-long-lived-workers)** clears the resolved URLs and
per-request sync marks, for applications running the toolkit under a long-lived worker.

## To 2.3

:::note Withdrawn
2.3.0 is no longer available to install. This section is kept because both features below shipped
unchanged in 2.4 — read it as "what 2.4 brought along", and target `^2.4`.
:::

Additive, with one behaviour change worth knowing about.

### Assets now also publish under `laravel-assets`

In addition to `{short-name}::assets`. That tag is what the Laravel application skeleton runs from
Composer's `post-update-cmd`, so your assets are republished after every `composer update` in an
application that keeps the default script — the same mechanism Horizon and Telescope rely on.

Laravel accumulates publish groups per path, so both tags publish the same files and an untagged
`vendor:publish` is unaffected. **No action needed**, but be aware that a consumer's
`composer update` may now overwrite hand-edited files under `public/vendor/{short-name}` — that hook
runs with `--force`.

### The asset mirror

[`Support\PublishedAssets`](/assets#the-asset-mirror) keeps `public/vendor/{short-name}` in step with
your `dist/` directory without anyone running a command. It is registered automatically by
`hasAssets()`; opt out with:

```php
$packager->hasAssets(mirror: false);
```

## To 2.2

Additive: [events](/events), [optimize commands](/optimize) and the
[configurable publish tag separator](/publishing#changing-the-tag-separator).

## To 2.1.1

A bug-fix release, but several of the fixes changed behaviour that was previously broken — so
something in your package may start working, and a workaround you wrote may become redundant.

| Was broken | Now |
|---|---|
| `hasAssets()` stored a path without `../`, so the assets tag published nothing | Publishes correctly. **Remove any manual `publishes()` workaround.** |
| `hasTranslations()` rejected the first supported language, and all region locales | `pt_BR`, `en-US` and the rest are accepted |
| `hasViews()` with a custom path failed either relative or absolute | Both resolve |
| `routes`, `view-components`, `view-component-namespaces` tags did nothing | All three publish. Routes go to `routes/vendor/{short-name}/` |
| `publishMigrations()` ignored an explicit file list | Publishes exactly the files you named |
| List-style component arrays got numeric aliases (`1`, `2`) | Only string keys become aliases |
| `packageCommands()` was never called | The override is honoured |
| The install command called `exit(0)` on a declined production prompt | Returns an exit code |
| `getVersion()` threw for a package with no composer `name` | Returns `null` |

### Renamed

`bootVewComposers()` → `bootViewComposers()`. The misspelling is kept as a deprecated alias; if you
override it, move to the correct spelling.

### Deprecated

`Contracts\HasAbout` will be removed in 3.0. `Packable` already declares `aboutData()`, and
`ProvidesPackageServices` extends `Packable`, so `PackageServiceProvider` covers it:

```php
class BlogServiceProvider extends PackageServiceProvider implements HasAbout // [tl! --]
class BlogServiceProvider extends PackageServiceProvider implements Packable // [tl! ++]
```

## To 2.1

**Laravel 10 and 11 support was removed.** The requirement is now Laravel 12 (>= 12.61.1) or
13 (>= 13.12.0).

Both branches had passed security-support end of life, and the June 2026 advisories — including the
High-severity CRLF injection CVE-2026-48019 — were patched only in Laravel 12.60+ and 13.9+, never
backported. There is no secure release on those branches, so the minimums are pinned to the first
patched versions.

```json title="composer.json"
{
    "require": {
        "nyoncode/laravel-package-toolkit": "^2.1"
    }
}
```

No code changes are required. If your package must still support Laravel 10 or 11, stay on
`~2.0.0` — and consider what that implies for the applications installing it.

## To 2.0 from 1.x

| Change | Action |
|---|---|
| Laravel 9 support dropped | Stay on `^1.0` if you need it |
| Minimum PHP raised to 8.2 | Update your `require.php` constraint |
| Laravel 13 support added | — |
| [Timeless migrations](/migrations#timeless-migrations) added | Non-breaking; timestamped migrations are unaffected |

```json title="composer.json"
{
    "require": {
        "php": "^8.2",
        "nyoncode/laravel-package-toolkit": "^2.0"
    }
}
```

Then `composer update`. No code changes are required unless your package explicitly depends on
Laravel 9 or PHP 8.1.

### Timeless migrations, in detail

The addition is non-breaking, but it changes what happens when a migration file has no date prefix.
Before 2.0 such a file was published verbatim, which produced a migration Laravel could not order.
From 2.0, the toolkit detects the missing prefix and generates one at publish time.

If your package ships migrations with a date prefix, nothing changes. If it shipped prefix-less files
and you worked around the ordering by hand, you can drop the workaround.

## Compatibility promise

- **Major** versions may contain breaking changes.
- **Minor** versions stay backward compatible within a major.
- **Patch** versions contain bug fixes and security updates only.

Recommended constraint:

```json
{
    "require": {
        "nyoncode/laravel-package-toolkit": "^2.4"
    }
}
```

## Looking ahead to 3.0

Known removals:

- `Contracts\HasAbout` — use `Packable`.
- `bootVewComposers()` — use `bootViewComposers()`.

One rule holds the whole plan for 3.0 together: nothing gets taken away without a replacement that
is already in place.
