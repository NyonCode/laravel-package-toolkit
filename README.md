# 📦 Laravel package toolkit

Laravel Package toolkit is a powerful tool designed to streamline the process of creating and managing packages for
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
- [About Command](#about-command)
- [Testing](#testing)
- [License](#license)

## Installation

You can install the package via composer:

```bash
composer require nyoncode/laravel-package-toolkit
```

## Usage

### Basic Configuration

To use Laravel Package Builder, create a ServiceProvider for your package that extends
`NyonCode\LaravelPackageToolkit\PackageServiceProvider`:

```php
use NyonCode\LaravelPackageToolkit\PackageServiceProvider;
use NyonCode\LaravelPackageToolkit\Packager;

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
use NyonCode\LaravelPackageToolkit\PackageServiceProvider;
use NyonCode\LaravelPackageToolkit\Packager;

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

Define a custom short name for the package:

```php
$packager->hasShortName('custom-short-name')
```

## Routing

To enable routing in your package:

```php
$packager->hasRoutes();
```

## Migrations

To enable migrations:

```php
$packager->hasMigrations();
```

### Use migration without publishing

```php
$packager->canLoadMigrations();
```

## Translations

To enable translations:

```php
$packager->hasTranslations();
```

## Views

To enable views:

```php
$packager->hasViews();
```

## View Components

To register multiple view components:

```php
$packager->hasComponents(
    prefix: 'nyon', 
    components: [
        'data-table' => DataTable::class,
        'modal' => Modal::class,
    ]
);
```

To register a single view component with an optional alias:

```php
$packager->hasComponent('nyon', Alert::class, 'custom-alert');
```

You can then use these components in your Blade templates:

```blade
<x-nyon-data-table :data="$users"/>
<x-nyon-modal title="User Details">
    <!-- Modal content -->
</x-modal>
<x-nyon-custom-alert type="warning" message="This is a warning!"/>
```

## About Command

Laravel Package Builder provides methods to add package information to Laravel's php artisan about command.

### hasAbout()

```php
$packager->hasAbout();
```

### hasVersion()

```php
$packager->hasVersion('1.0.0'); 
```

### Customizing About Command Data

```php
public function aboutData(): array
{
    return [
        'Repository' => 'https://github.com/your/package',
        'Author' => 'Your Name',
    ];
}
```

## Testing

```bash
composer test
```

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

