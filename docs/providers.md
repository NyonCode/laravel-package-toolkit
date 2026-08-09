---
title: Publishable providers
description: Ship a service provider template that consumers publish into app/Providers and own from then on.
---

# Publishable providers

```php
public function hasProvider(string $provider): static
public function hasProviders(array $providers): static
```

Some packages want the consumer to have their **own** provider — a place to register their event
listeners, override a binding, or configure the package in code rather than in a config file.
Laravel Cashier and Fortify both do this. `hasProviders()` makes the template publishable.

```php title="src/BlogServiceProvider.php"
$packager
    ->name('Blog')
    ->hasProviders([
        '../stubs/BlogServiceProvider.stub',
    ]);
```

```bash
php artisan vendor:publish --tag=blog::providers
```

```text
app/Providers/BlogServiceProvider.php
```

## Paths are relative to your provider's directory

Unlike every other builder, there is no directory argument — the path you give *is* the path, and it
resolves from the directory your provider lives in. Hence the `../` in the examples: from `src/`,
`../stubs/BlogServiceProvider.stub` reaches the package's `stubs/` directory.

```php
$packager->hasProvider('../stubs/BlogServiceProvider.stub');
$packager->hasProviders([
    '../stubs/BlogServiceProvider.stub',
    '../stubs/BlogEventServiceProvider.stub',
]);
```

A path that does not resolve throws `FileNotFoundException` at registration.

## `.stub` publishes as `.php`

The destination is `app_path('Providers/'.$file->getBaseFileName().'.php')` — the basename without
its extension, plus `.php`:

| Source | Destination |
|---|---|
| `stubs/BlogServiceProvider.stub` | `app/Providers/BlogServiceProvider.php` |
| `stubs/BlogEventServiceProvider.stub` | `app/Providers/BlogEventServiceProvider.php` |
| `providers/BlogServiceProvider.php` | `app/Providers/BlogServiceProvider.php` |

Shipping the template as a `.stub` is worth doing. A `.php` file inside your package is loaded by
your test suite, analysed by PHPStan, and reported by coverage — none of which you want for a
template that references classes only present after installation.

## Writing the template

```php title="stubs/BlogServiceProvider.stub"
<?php

namespace App\Providers;

use Acme\Blog\Blog;
use Illuminate\Support\ServiceProvider;

class BlogServiceProvider extends ServiceProvider
{
    /**
     * Register any blog services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any blog services.
     */
    public function boot(): void
    {
        Blog::authorizeUsing(function ($request) {
            return $request->user()?->isBlogAuthor() ?? false;
        });
    }
}
```

The namespace must be `App\Providers` — that is where it lands. Since it is a stub, nothing in your
package tries to autoload it.

## Registering the published provider

Publishing writes the file; it does not register it. In Laravel 11 and later that means
`bootstrap/providers.php`:

```php title="bootstrap/providers.php"
return [
    App\Providers\AppServiceProvider::class,
    App\Providers\BlogServiceProvider::class, // [tl! ++]
];
```

The [install command](/install-command) has a helper for the older `config/app.php` layout:

```php
use NyonCode\LaravelPackageToolkit\Commands\InstallCommand;

$packager->hasInstallCommand(function (InstallCommand $command) {
    $command
        ->publishProviders()
        ->copyAndRegisterServiceProviderInApp(\App\Providers\BlogServiceProvider::class);
});
```

:::warning `copyAndRegisterServiceProviderInApp()` edits `config/app.php` only
It looks for a `'providers' => [ … ]` array in `config/app.php` and appends to it. A Laravel 11+
application has no `providers` key there — many have no `config/app.php` at all — so the call warns
and does nothing:

```text
config/app.php not found. Skipping provider registration.
```

For a modern application, publish the provider and tell the consumer to add the line to
`bootstrap/providers.php`. Printing the instruction from an
[after-installation hook](/install-command#hooks) is the honest version:

```php
$command->afterInstallation(function (InstallCommand $command) {
    $command->comment('Add App\Providers\BlogServiceProvider::class to bootstrap/providers.php');
});
```
:::

## Providers versus config

A publishable provider and a config file solve overlapping problems. The distinction that holds up:

| | Config file | Published provider |
|---|---|---|
| Values | scalars, arrays, class strings | closures, callbacks, runtime logic |
| Survives `config:cache` | yes | n/a |
| Discoverable | `config/blog.php` is obvious | needs documenting |
| Right for | limits, drivers, feature flags | authorization callbacks, macros, custom resolvers |

Anything a closure can express and an array cannot belongs in a provider. Everything else belongs in
config.

## Introspection

```php
$packager->isProvidable(); // bool
$packager->providers();    // Support\SplFileInfo[]
```

## Testing

```php
test('the package publishes its provider template', function () {
    $this->artisan('vendor:publish --tag=blog::providers')->assertExitCode(0);

    expect(app_path('Providers/BlogServiceProvider.php'))->toBeFile()
        ->and(file_get_contents(app_path('Providers/BlogServiceProvider.php')))
        ->toContain('namespace App\Providers;');
});
```
