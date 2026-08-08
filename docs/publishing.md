---
title: Publishing
description: Every publish tag, where each resource lands, and how to change the tag format.
---

# Publishing

Every publishable resource is registered under a tag built from your package's short name, a
separator, and a group name:

```text
{short-name}{separator}{group}
```

The default separator is `::`, so a package named `Blog` publishes under `blog::config`,
`blog::views`, and so on.

```bash
php artisan vendor:publish --tag=blog::config
php artisan vendor:publish --tag=blog::views --force
php artisan vendor:publish --provider="Acme\Blog\BlogServiceProvider"
```

Publishing is registered during `boot()`, and only when the application is running in the console —
a web request never builds the publish map.

## Every tag

| Tag | Declared by | Destination |
|---|---|---|
| `blog::assets` | `hasAssets()` | `public/vendor/blog/` |
| `laravel-assets` | `hasAssets()` | `public/vendor/blog/` |
| `blog::config` | `hasConfig()` | `config/{filename}` |
| `blog::factories` | `hasFactories()` | `database/factories/{Name}.php` |
| `blog::migrations` | `hasMigrations()` | `database/migrations/` |
| `blog::providers` | `hasProviders()` | `app/Providers/{Name}.php` |
| `blog::routes` | `hasRoutes()` | `routes/vendor/blog/{filename}` |
| `blog::seeders` | `hasSeeders()` | `database/seeders/{Name}.php` |
| `blog::stubs` | `hasStubs()` | `stubs/blog/{filename}` |
| `blog::translations` | `hasTranslations()` | `lang/vendor/blog/` |
| `blog::view-components` | `hasComponents()` | `app/View/Components/blog/{Dir}/` |
| `blog::view-component-namespaces` | `hasComponentNamespaces()` | `app/View/Components/blog/{Dir}/` |
| `blog::views` | `hasViews()` | `resources/views/vendor/blog/` |

Broadcast channels are the one declared resource with no tag — see
[why they are not publishable](/broadcast-channels#channels-are-deliberately-not-publishable).

### The `laravel-assets` tag

Assets register under two tags. The second is Laravel's own convention, and it is what the
application skeleton runs from Composer's `post-update-cmd`:

```bash
php artisan vendor:publish --tag=laravel-assets --ansi --force
```

One command republishes every installed package's assets after every `composer update` — Horizon,
Telescope and Nova all rely on it. Laravel accumulates publish groups per path, so both tags publish
the same files, and an untagged `vendor:publish` is unaffected.

## Which override points are real

Publishing means different things for different resources, and it is worth being precise, because
"publish it and edit it" is only true for half of this table.

| Resource | After publishing |
|---|---|
| Views | **Overrides.** Laravel checks `views/vendor/blog/` first, per view. |
| Translations | **Overrides.** Same, per key. |
| Config | **Merges.** The published file wins per top-level key; your defaults fill the rest. |
| Migrations | **Replaces**, if you were not also loading them. |
| Seeders, factories, stubs | **Copies.** Ready to use; nothing overrides anything. |
| Routes | **Copies, inert.** Your package still loads its own — see [Routes](/routes#publishing). |
| Providers | **Copies, inert.** The consumer must register it. |
| View components | **Forks.** Laravel has no lookup order for component classes. |
| Assets | **Copies** to the same place the mirror uses. |

## Changing the tag separator

```php
public function hasPublishTagSeparator(string|array $separator): static
```

Some ecosystems prefer the flat `package-group` form. Pass a different separator:

```php
$packager
    ->name('Blog')
    ->hasPublishTagSeparator('-')
    ->hasConfig();
```

```bash
php artisan vendor:publish --tag=blog-config
```

### Registering both forms

Pass an array, and every group registers under **each** separator:

```php
$packager->hasPublishTagSeparator(['::', '-']);
```

```bash
# Both work, and publish the same files.
php artisan vendor:publish --tag=blog::config
php artisan vendor:publish --tag=blog-config
```

The first separator is the **primary** one: it is what the [install command](/install-command) uses
when it builds the tags it publishes. Put the form you consider canonical first.

This is the migration path for a package changing its convention — ship both for a release, document
the new one, drop the old one in the next major.

## Publishing everything

```bash
# By tag — one group.
php artisan vendor:publish --tag=blog::config

# By provider — every group the package registered.
php artisan vendor:publish --provider="Acme\Blog\BlogServiceProvider"

# Overwrite files that already exist.
php artisan vendor:publish --tag=blog::views --force
```

`--force` is the one to warn consumers about: it overwrites their edits without asking. It is also
what a deploy script needs for assets, which is why the `laravel-assets` hook uses it.

## Publishing from the install command

The [install command](/install-command) publishes tags on the consumer's behalf, with progress
output and a summary:

```php
use NyonCode\LaravelPackageToolkit\Commands\InstallCommand;

$packager->hasInstallCommand(function (InstallCommand $command) {
    $command
        ->publishConfig()
        ->publishMigrations()
        ->publishViews();
});
```

## Checking what is registered

```php
use Illuminate\Support\ServiceProvider;

ServiceProvider::pathsToPublish(null, 'blog::config');
// ['/…/vendor/acme/blog/config/blog.php' => '/…/config/blog.php']

ServiceProvider::publishableGroups();
// every registered tag, from every package
```

Useful in tests, and useful when a tag mysteriously publishes nothing:

```php
test('the config group is registered', function () {
    expect(ServiceProvider::pathsToPublish(null, 'blog::config'))->not->toBeEmpty();
});
```

:::warning A tag that publishes nothing still exits 0
`vendor:publish` with an unknown tag succeeds silently. So does the install command's step for it.
If a publish appears to do nothing, assert on `pathsToPublish()` rather than on the exit code.
:::

## Naming conventions

The short name is the whole namespace you get. Two rules follow:

- **Prefix anything that lands in a shared directory.** Seeders, factories and config files publish
  flat into directories the application owns. `BlogPostSeeder`, not `PostSeeder`; `blog.php`, not
  `settings.php`.
- **Do not change the short name after release.** Every tag, path and namespace moves with it, and
  consumers' published overrides stop being found. See
  [Short name](/packager#short-name).
