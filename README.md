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
- [Config](#config)
- [Routing](#routing)
- [Migrations](#migrations)
- [Translations](#translations)
- [Commands](#commands)
- [Views](#views)
- [View Components](#view-components)
- [View Component Namespaces](#view-component-namespaces)
- [View Composers](#view-composers)
- [Shared Data](#view-shared-data)
- [Assets](#assets)
- [Providers](#providers)
- [About Command](#about-command)
- [Publishing](#publishing)
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
use NyonCode\LaravelPackageToolkit\Contracts\Packable;

class MyAwesomePackageServiceProvider extends PackageServiceProvider implements
    Packable
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
use NyonCode\LaravelPackageToolkit\Contracts\Packable;

class AdvancedPackageServiceProvider extends PackageServiceProvider implements
    Packable
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

### Conditional registration resources

You can also use the `when()` method to conditionally register resources:

```php
use NyonCode\LaravelPackageToolkit\PackageServiceProvider;
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\Contracts\Packable;

class ConditionalPackageServiceProvider extends PackageServiceProvider implements
    Packable
{
    public function configure(Packager $packager): void
    {
        $packager
            ->name('Conditional package')
            ->hasRoutes(['api.php', 'web.php'])
            ->hasMigrations('custom-migrations')
            ->hasTranslations('lang')
            ->hasViews('custom-views')
            ->when($this->isInLocal(), function ($packager) {
                $packager->hasConfig('local-config.php')
                $packager->hasCommands();
            })->when($this->isInProduction(), function ($packager) {
                $packager->hasConfig('production-config.php')
                $packager–>hasRoutes('web.php');
            });
    }
}
```
Local and production resources will be registered when the `isInLocal()` and `isInProduction()` methods return `true`.

---


## Lifecycle Hooks

Here is a list of all available life cycle hooks:

| **Hook Method**        | **Description**                      |
| ---------------------- | ------------------------------------ |
| `registeringPackage()` | Called before `register()` is called |
| `registeredPackage()`  | Called after `register()` is called  |
| `bootingPackage()`     | Called before `boot()` is called     |
| `bootedPackage()`      | Called after `boot()` is called      |

---

## Name

Define a name for the package:

```php
$packager->name('Package name');
```
---

## Short name

Define a custom short name for the package.
The hasShortName method is used to modify the name defined by `name()` if you prefer not to use the short version from
`$packager->name('Package name')`:

```php
$packager->hasShortName('custom-short-name');
```
---
## Config

To enable configuration in your package:

```php
$packager->hasConfig();
```
---
By default, this will load configuration from the `config` directory. For custom config files:

```php
$packager->hasConfig(['config.php', 'other-config.php']);
```

Or for specific file paths:

```php
$packager->hasConfig([
    '../www/config/config.php',
    '../api/config/other-config.php',
]);
```

To use an alternative directory for config files.

```php
$package->hasConfig(directory: 'customConfig');
```
---

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
$packager->hasRoute(['../www/routes/web.php', '../api/routes/api.php']);
```

To use an alternative directory for route files.

```php
$package->hasRoute(['web.php'], 'webRouter');
```
---

## Migrations

To enable migrations:

```php
$packager->hasMigrations();
```

This loads migrations from the `database/migrations` directory. For a custom directory:

```php
$packager->hasMigrations('custom-migrations');
```

Or for specific file paths:

```php
$packager->hasMigrations([
    '../www/database/migrations/2023_01_01_000000_create_users_table.php',
    '../api/database/migrations/2023_01_01_000001_create_roles_table.php',
]);
```

To use an alternative directory for migration files.

```php
$package->hasMigrations(
    ['2023_01_01_000000_create_users_table.php'],
    'userMigrations'
);
```

For more information about migrations, see [Laravel migrations](https://laravel.com/docs/9.x/migrations).

### Use migration without publishing

```php
$packager->canLoadMigrations();
```
---

## Translations

To enable translations:

```php
$packager->hasTranslations();
```

This loads translations from the `lang` directory and automatically supports JSON translations.

For a custom directory:

```php
$packager->hasTranslations('../custom-lang-directory');
```
---

## Commands

To enable commands:

```php
$packager->hasCommands();
```

Defaults to loading commands from the `Commands` directory.
To use an alternative directory for command files.

```php
$packager->hasCommands(directory: 'custom-commands');
```

For single command:

```php
$packager->hasCommand('\Vendor\Package\Commands\CustomCommand::class');
```

Or for specific file names:

```php
$packager->hasCommands([
    '\Vendor\Package\Commands\CustomCommand::class',
    '\Vendor\Package\Commands\OtherCommand::class',
]);
```

For more information about commands, see [Laravel commands](https://laravel.com/docs/12.x/artisan).

---

## Views

To enable views:

```php
$packager->hasViews();
```

This loads views from the `resources/views` directory. For a custom directory:

```php
$packager->hasViews('custom-views');
```
---

## View Components

To register multiple view components:

```php
$packager->hasComponents(
    prefix: 'nyon',
    components: [
        'data-table' => DataTable::class,
        'modal' => Modal::class,
        Sidebar::class,
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
<x-nyon-sidebar id="sidebar"/>
<x-nyon-custom-alert type="warning" message="This is a warning!"/>
```
---

## View Component Namespaces

To register multiple view component namespaces:

```php
$packager->hasComponentNamespaces(
    namespaces: [
        'nyon' => 'App\View\Components\Alert',
        'admin' => 'App\View\Components\Modal',
    ]
);
```

To register a single view component namespace with an optional alias:

```php
$packager->hasComponentNamespace('nyon', 'App\View\Components\Alert');
```

You can then use these namespaces in your Blade templates:

```blade
<x-nyon::alert :data="$users"/>
<x-admin::modal title="User Details">
    <!-- Modal content -->
</x-admin-modal>
```
---

## View Composers

To register multiple view composers:

```php
$packager
    ->hasViewComposer(
        views: 'nyon',
        composers: fn($view) => $view->with('test', 'test-value')
    )->hasViewComposer(
        views: ['viewName', 'anotherViewName'],
        composers: MyViewComposer::class
    )
);
```
---

## View Shared Data

To add shared data to views:

```php
$packager->hasSharedDataForAllViews(['key', 'value']);
```

This adds a key-value pair to the shared data array in the view.

For more information about shared data, see [Laravel shared data](https://laravel.com/docs/12.x/views#shared-data).

---

## Assets

To enable assets:

```php
$packager->hasAssets();
```

This loads assets from the `public` directory. For a custom directory:

```php
$packager->hasAssets('../dist');
```
---

## Providers

To enable service providers:

```php
$packager->hasProvider('../stubs/MyProvider.stub');
```
Support for multiple service providers:

```php
$packager->hasProvider('../stubs/MyProvider.stub')
    ->hasProvider('../stubs/MyOtherProvider.stub');
```

---

## About Command

Laravel Package Builder provides methods to add package information to Laravel's php artisan about command.

### hasAbout()

The hasAbout() method allows you to include your package's information in the Laravel About command. By default, it will
include the package's version.

```php
$packager->hasAbout();
```

### hasVersion()

The hasVersion() method lets you manually set the version of your package:

```php
$packager->hasVersion('1.0.0');
```

If no version is manually set, the package will automatically retrieve the version from your composer.json file.

### Customizing About Command Data

You can extend the about command information by implementing the `aboutData()` method in your service provider:

```php
public function aboutData(): array
{
    return [
        'Repository' => 'https://github.com/your/package',
        'Author' => 'Your Name',
    ];
}
```

This method allows you to add custom key-value pairs to the About command output for your package.
When you run `php artisan about`, your package's information will be displayed in a dedicated section.
This implementation allows for flexible and easy inclusion of package metadata in Laravel's system information command.

---

## Publishing

For publishing, you can use the following commands:

```bash
php artisan vendor:publish
```
`vendor:publish` show all the tags that can be used for publishing.


### Example of using tags:

Use `php artisan vendor:publish --tag=package-name::config` for publish configuration files.

---

## Testing

```bash
composer test
```

---

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
