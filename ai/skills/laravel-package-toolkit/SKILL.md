---
name: laravel-package-toolkit
description: Build or change a Laravel package that uses nyoncode/laravel-package-toolkit. Use when writing a service provider that extends PackageServiceProvider, when calling any hasX() builder on Packager (hasConfig, hasRoutes, hasMigrations, hasViews, hasAssets, hasViteAssets, hasCommands, hasInstallCommand…), when wiring publish tags or an install command for a package, or when a package's config/routes/views/migrations/translations/assets are declared but not loading or not publishing.
---

# Laravel Package Toolkit

A package built on this toolkit describes itself once, in `configure(Packager $packager)`, and the
base provider does the register/boot/publish work. There is no hidden magic: every `hasX()` on the
description has a matching `bootX()` or `publishX()` on the provider, and any of them can be
overridden.

## Read this first

The complete API reference ships with the package. **Read it before writing a `configure()` body** —
it is the version that matches the installed release, whereas your training data is not:

```
vendor/nyoncode/laravel-package-toolkit/ai/AGENTS.md
```

If the toolkit's MCP server is registered, prefer its tools over grepping: `search_docs` for a
concept, `describe_api` for an exact signature, `get_doc` for a whole page.

## The shape of the work

```php title="src/BlogServiceProvider.php"
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\PackageServiceProvider;

class BlogServiceProvider extends PackageServiceProvider
{
    public function configure(Packager $packager): void
    {
        $packager
            ->name('Blog')
            ->hasConfig()
            ->hasRoutes()
            ->hasViews()
            ->hasMigrations();
    }
}
```

`name()` is the only required call. Everything else is opt-in, order does not matter, and each
builder returns `static`.

## Four things that are easy to get wrong

**Paths are relative to the provider file's directory.** Defaults look like `../config` and
`../resources/views` because they assume the provider sits one level deep, in `src/`. A provider in
`src/Providers/` is auto-corrected up one level; any other layout needs the `$directory` argument
passed explicitly. Never guess — check where the provider file actually is.

**Resources differ in whether they load, publish, or both.** Config, routes, views, translations,
middleware, commands and events are *loaded*. Seeders, factories, stubs and extra providers are
*publish-only* — nothing happens at boot. Broadcast channels are *load-only* and deliberately not
publishable. Migrations are publish-only **unless** you call `canLoadMigrations()`. Declaring a
publish-only resource and then wondering why it is not active is the single most common mistake.

**Extending the toolkit means touching four places, not one.** If the task is adding a new resource
type *to the toolkit itself*: a `Concerns/HasX` trait for the declaration, a `bootX()` in
`Support/Concerns/BootsPackageResources` added to the ordered chain, a `publishX()` in
`Support/Concerns/PublishesPackageResources`, and — the one that fails silently — an entry in
`InstallCommand::getInstallationSteps()`, whose hardcoded step map drops any tag missing from it
without an error.

**Publish tags are `{shortName}::{group}`.** The separator is configurable with
`hasPublishTagSeparator()`, which accepts an array to register several forms at once.

## Verify, don't assume

After changing a `configure()` body, run through the checklist at the end of `ai/AGENTS.md`. The
two fastest checks: `php artisan vendor:publish --list` shows every tag that actually got
registered, and `php artisan <short-name>:install --no-interaction` on a scratch app shows what the
install command actually publishes.

When working *on the toolkit repository itself*, `composer test`, `composer lint` (PHPStan level 5
over `src`) and `composer pint` all have to pass; see that repository's `AGENTS.md`.
