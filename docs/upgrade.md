---
title: Upgrade guide
description: What changed in each release, what breaks, and what to do about it.
---

# Upgrade guide

The toolkit follows [Semantic Versioning](https://semver.org/). Minor releases add functionality
without breaking existing code; major releases may break.

| Constraint | Gets you |
|---|---|
| `^2.4` | the current line, including seeders, factories, stubs and broadcast channels |
| `^2.0` | Laravel 12/13, PHP 8.2+ |
| `~2.0.0` | the last line supporting Laravel 10 and 11 |
| `^1.0` | Laravel 9 |

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

The [roadmap](/roadmap) covers what 3.0 is being built around, and states the rule the whole plan is
held to: nothing gets taken away without a replacement that is already in place.
