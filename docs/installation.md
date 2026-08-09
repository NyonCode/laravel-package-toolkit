---
title: Installation
description: Add the toolkit to your Laravel package, wire up auto-discovery, and lay out the directories the defaults expect.
---

# Installation

The toolkit is a dependency of *your package*, not of the application that installs your package.
Add it to the package you are building.

```bash
composer require nyoncode/laravel-package-toolkit
```

In your package's `composer.json` it belongs in `require`, because your service provider extends a
class from it at runtime:

```json title="composer.json"
{
    "name": "acme/blog",
    "require": {
        "php": "^8.2",
        "nyoncode/laravel-package-toolkit": "^2.4" // [tl! ++]
    },
    "autoload": {
        "psr-4": {
            "Acme\\Blog\\": "src/"
        }
    },
    "extra": { // [tl! focus:start]
        "laravel": {
            "providers": [
                "Acme\\Blog\\BlogServiceProvider"
            ]
        }
    } // [tl! focus:end]
}
```

The `extra.laravel.providers` entry is Laravel's own package discovery — the toolkit does not
replace it. Without it, an application would have to register your provider by hand.

## Directory layout

Every `hasX()` method takes an explicit path, so no layout is mandatory. But the defaults are worth
matching, because matching them means calling `hasConfig()` instead of
`hasConfig(directory: '../src/resources/configuration')`.

```text
acme/blog/
├── composer.json
├── config/
│   └── blog.php                    hasConfig()
├── database/
│   ├── factories/                  hasFactories()
│   ├── migrations/                 hasMigrations()
│   └── seeders/                    hasSeeders()
├── dist/                           hasAssets()
│   ├── css/blog.css
│   └── js/blog.js
├── lang/                           hasTranslations()
│   ├── en/messages.php
│   └── en.json
├── resources/
│   └── views/                      hasViews()
├── routes/
│   ├── web.php                     hasRoutes()
│   └── channels.php                hasBroadcastChannels()
├── stubs/                          hasStubs()
└── src/
    ├── BlogServiceProvider.php
    ├── Commands/                   hasCommands()
    └── View/Components/
```

Two details in that tree are easy to miss and are covered in full on [The Packager](/packager):

- Paths are resolved from **the directory your service provider lives in** — normally `src/` —
  which is why nearly every default starts with `../`.
- `hasCommands()` is the exception: its default directory is `Commands`, *without* `../`, because
  commands are PHP classes that live inside `src/` alongside the provider.

:::tip Provider in a subdirectory
If you keep your provider at `src/Providers/BlogServiceProvider.php`, the toolkit detects the
`src/Providers` suffix and trims it back to `src`, so every default path keeps working unchanged.
:::

## Your first provider

```php title="src/BlogServiceProvider.php"
namespace Acme\Blog;

use NyonCode\LaravelPackageToolkit\Contracts\Packable;
use NyonCode\LaravelPackageToolkit\PackageServiceProvider;
use NyonCode\LaravelPackageToolkit\Packager;

class BlogServiceProvider extends PackageServiceProvider implements Packable
{
    public function configure(Packager $packager): void
    {
        $packager->name('Blog');
    }
}
```

`configure()` is the only abstract method: a provider that does not implement it will not compile.
Implementing `Packable` is optional — `PackageServiceProvider` already implements
`ProvidesPackageServices`, which extends `Packable` — but stating it makes the contract visible at
the top of the file, and every example in these docs does.

`name()` is the only required call. Leave it out and registration throws
`MissingNameException`, because the short name derived from it is what every publish tag, view
namespace and translation namespace is built from.

## Developing against a local application

While building a package it is convenient to test it inside a real application without publishing to
Packagist. Add a path repository to the *application's* `composer.json`:

```json title="my-app/composer.json"
{
    "repositories": [ // [tl! ++:start]
        { "type": "path", "url": "../packages/blog" }
    ], // [tl! ++:end]
    "require": {
        "acme/blog": "@dev" // [tl! ++]
    }
}
```

Composer symlinks the directory, so edits in the package are live in the application immediately.

For testing the package on its own — no host application at all — see [Testing](/testing), which
covers the Orchestra Testbench setup this toolkit itself uses.

## Verifying the install

```bash
php artisan about
```

The toolkit registers its own section, so a correct install shows:

```text
  Laravel Package Toolkit ......................................
  Version ................................................ 2.4.0
```

Your package gets its own section too, once you opt in with `hasAbout()` — see
[The about command](/about-command).
