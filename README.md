# 📦 Laravel package builder

Laravel Package Builder is a powerful tool designed to streamline the process of creating and managing packages for
Laravel. It provides a set of intuitive abstractions and helper methods for common package development tasks, enabling
developers to focus on building features rather than boilerplate code.

## Features

- Simple and expressive package configuration
- Automatic handling of routes, migrations, translations, and views
- Support for view components
- Built-in exception handling for package-specific errors
- Comprehensive language support

## Table of Contents

- [Installation](#installation)
- [Usage](#usage)
    - [Basic Configuration](#basic-configuration)
    - [Advanced Configuration](#advanced-configuration)
- [Lifecycle Hooks](#lifecycle-hooks)
- [Name](#name)
- [Short name](#short-name)
- [Routing](#routing)
- [Migrations](#migrations)
- [Translations](#translations)
- [Views](#views)
- [View Components](#view-components)
- [Testing](#testing)
- [License](#license)

## Installation

You can install the package via composer:

```bash
composer require nyoncode/laravel-package-builder
```

## Usage

### Basic Configuration

To use Laravel Package Builder, create a ServiceProvider for your package that extends
`NyonCode\LaravelPackageBuilder\PackageServiceProvider`:

```php
use NyonCode\LaravelPackageBuilder\PackageServiceProvider;
use NyonCode\LaravelPackageBuilder\Packager;

class MyAwesomePackageServiceProvider extends PackageServiceProvider
{
    public function configure(Packager $packager): void
    {
        $packager
            ->name('My Awesome Package')
            ->hasConfig()
            ->hasRoutes()
            ->hasMigrations()
            ->hasTranslations()
            ->hasViews();
    }
}
```

### Advanced Configuration

For more control over your package configuration, you can use additional methods and specify custom paths:

```php
use NyonCode\LaravelPackageBuilder\PackageServiceProvider;
use NyonCode\LaravelPackageBuilder\Packager;

class AdvancedPackageServiceProvider extends PackageServiceProvider
{
    public function configure(Packager $packager): void
    {
        $packager
            ->name('Advanced package')
            ->hasShortName('adv-pkg')
            ->hasConfig('custom-config.php')
            ->hasRoutes(['api.php', 'web.php'])
            ->hasMigrations('custom-migrations')
            ->hasTranslations('lang')
            ->hasViews('custom-views')
            ->hasComponents([
                'data-table' => DataTable::class,
                'modal' => Modal::class,
            ]);
    }

    public function registeringPackage(): void
    {
        // Custom logic before package registration
    }

    public function bootingPackage(): void
    {
        // Custom logic before package boot
    }
}
```

## Lifecycle Hooks

Here is a list of all available life cycle hooks:

| **Hook Method**        | **Description**                      |
|------------------------|--------------------------------------|
| `registeringPackage()` | Called before `register()` is called |
| `registeredPackage()`  | Called after `register()` is called  |
| `bootingPackage()`     | Called before `boot()` is called     |
| `bootedPackage()`      | Called after `boot()` is called      |

___

## Name

Define a name for the package:

```php
$packager->name('Package name')
```

## Short name

Define a custom short name for the package.
The hasShortName method is used to modify the name defined by `name()` if you prefer not to use the short version from
`$packager->name('Package name')`:

```php
$packager->hasShortName('custom-short-name')
```

## Routing

To enable routing in your package:

```php
$packager->hasRoutes();
```

By default, this will load routes from the `routes` directory. For custom route files:

```php
$packager->hasRoutes(['api.php', 'web.php']);
```

Or for specific file paths:

```php
$packager->hasRoute([
        '../www/routes/web.php',
        '../api/routes/api.php'
    ])
```

To use an alternative directory for route files.

```php
$package->hasRoute(['web.php'], 'webRouter')
```

## Migrations

To enable migrations:

```php
$packager->hasMigrations();
```

This loads migrations from the `database/migrations` directory. For a custom directory:

```php
$packager->hasMigrations('custom-migrations');
```

## Translations

To enable translations:

```php
$packager->hasTranslations();
```

This loads translations from the `lang` directory and automatically supports JSON translations.

## Views

To enable views:

```php
$packager->hasViews();
```

This loads views from the `resources/views` directory. For a custom directory:

```php
$packager->hasViews('custom-views');
```

## View Components

To register view components:

```php
$packager->hasComponents([
    'data-table' => DataTable::class,
    'modal' => Modal::class,
]);
```

You can then use these components in your Blade templates:

```blade
<x-data-table :data="$users"/>
<x-modal title="User Details">
    <!-- Modal content -->
</x-modal>
```

## Testing

```bash
composer test
```

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
