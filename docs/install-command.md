---
title: The install command
description: Give your users php artisan your-package:install — with presets, hooks, conditional publishing and progress output.
---

# The install command

```php
public function hasInstallCommand(?Closure $callback = null): static
```

One call registers `php artisan {short-name}:install`:

```php title="src/BlogServiceProvider.php"
use NyonCode\LaravelPackageToolkit\Commands\InstallCommand;

$packager
    ->name('Blog')
    ->hasConfig()
    ->hasMigrations()
    ->hasInstallCommand(function (InstallCommand $command) {
        $command->publishConfig()->publishMigrations();
    });
```

```bash
php artisan blog:install
```

```text
🚀 Installing Blog

(1/2) Publishing configuration...
  ✅ Published config
(2/2) Publishing migrations...
  ✅ Published migrations

✨ Blog installed successfully!

📋 Next steps:
  • Review configuration in config/blog.php
  • Run: php artisan migrate
```

The command is registered only when the application is running in the console, and only when
`hasInstallCommand()` (or a preset) was called.

## Signature

```text
{short-name}:install
    {--force : Force the operation to run when in production}
```

`--no-interaction` is not declared because every Artisan command has it. It has two effects here:
the banner and the closing summary are suppressed, and the production confirmation is skipped.

## What it publishes

Each `publishX()` adds a tag to the list. The command publishes them in a fixed order, not the order
you declared:

| Step | Tag |
|---|---|
| 1 | `config` |
| 2 | `migrations` |
| 3 | `seeders` |
| 4 | `factories` |
| 5 | `assets` |
| 6 | `translations` |
| 7 | `views` |
| 8 | `providers` |
| 9 | `routes` |
| 10 | `stubs` |

Config first and migrations second is the order that matters — anything a later step or a hook reads
from config is in place by then.

:::warning View components are silently skipped
`publishComponents()` and `publishComponentNamespaces()` add the `view-components` and
`view-component-namespaces` tags, and `publishEverything()` includes them — but the step table above
has no entry for either, and the command only runs steps it recognises. Nothing is published and
nothing is reported.

Publish them from an [after-installation hook](#hooks) instead:

```php
$command->afterInstallation(function (InstallCommand $command) {
    $command->call('vendor:publish', ['--tag' => 'blog::view-components']);
});
```
:::

## Publishing methods

Every method returns `$this`, so they chain.

```php
$command
    ->publishConfig()          // aliases: publishConfigFile(), publishConfigFiles()
    ->publishMigrations()
    ->publishSeeders()
    ->publishFactories()
    ->publishAssets()          // alias: publishPublicAssets()
    ->publishTranslations()    // aliases: publishTranslationFiles(), publishLanguageFiles()
    ->publishViews()           // alias: publishViewFiles()
    ->publishProviders()       // alias: publishServiceProviders()
    ->publishRoutes()          // alias: publishRouteFiles()
    ->publishStubs()
    ->publishComponents()      // alias: publishViewComponents()
    ->publishComponentNamespaces(); // alias: publishViewComponentNamespaces()
```

Bulk helpers:

```php
$command->publishEverything();  // every tag above — alias: publishAll()
$command->publishEssentials();  // config, migrations, assets
$command->publishCustom('config', 'my-extra-tag');
```

Inspection and reset:

```php
$command->getPublishTags();      // ['config', 'migrations']
$command->willPublish('views');  // false
$command->clearPublishTags();    // start over
```

## Conditional publishing

```php
$command
    ->publishConfig()
    ->publishIf(config('blog.api.enabled'), 'routes')
    ->publishUnless(app()->isProduction(), 'stubs')
    ->publishForEnvironment(['local', 'staging'], 'seeders', 'factories')
    ->publishForLocal('stubs', 'factories')
    ->publishForProduction('assets');
```

The condition is evaluated when the command is **constructed**, not when it runs. That is early —
during `register()`, or during `boot()` for the auto-install path — so anything time-sensitive or
input-dependent belongs in a hook instead.

## Hooks

```php
$packager->hasInstallCommand(function (InstallCommand $command) {
    $command
        ->beforeInstallation(function (InstallCommand $command) {
            $command->info('Checking requirements…');

            if (! extension_loaded('gd')) {
                $command->warn('The gd extension is missing; image features will be disabled.');
            }
        })
        ->publishConfig()
        ->publishMigrations()
        ->afterInstallation(function (InstallCommand $command) {
            if ($command->confirm('Run migrations now?', true)) {
                $command->call('migrate');
            }
        });
});
```

Both accept any number of callbacks and run them in registration order. Each receives the command,
so the whole Artisan API — `info()`, `warn()`, `confirm()`, `ask()`, `choice()`, `call()`, `table()`
— is available.

Hooks are **not** wrapped in a try/catch: an exception aborts the installation, which is usually
what you want from a failed prerequisite check.

:::warning Hooks run under `--no-interaction` too
The auto-install path runs the command non-interactively. `confirm()` returns its default in that
mode, so `confirm('Run migrations now?', true)` would run migrations unattended. Guard anything
destructive:

```php
$command->afterInstallation(function (InstallCommand $command) {
    if ($command->option('no-interaction')) {
        return;
    }

    // …interactive follow-up
});
```
:::

## Built-in helpers

### `askToStarRepoOnGitHub()`

```php
$command->askToStarRepoOnGitHub('https://github.com/acme/blog');
```

Registers an after-installation hook that offers to open the repository in the user's browser. It
returns immediately under `--no-interaction`. With no argument, the URL is read from your package's
`composer.json` — `homepage` first, then `support.source`.

### `copyAndRegisterServiceProviderInApp()`

```php
$command->copyAndRegisterServiceProviderInApp(\App\Providers\BlogServiceProvider::class);
```

Registers a before-installation hook that appends the class to the `providers` array in
`config/app.php`. See the [caveat for Laravel 11+](/providers#registering-the-published-provider) —
modern applications have no such array, and the hook warns and moves on.

## Presets

Four shortcuts for common shapes:

```php
$packager->hasQuickInstall();    // config + migrations + assets
$packager->hasMinimalInstall();  // config
$packager->hasFullInstall();     // publishEverything()
$packager->hasDevInstall();      // config + migrations + views + assets, routes in local only
```

Each is `hasInstallCommand()` with a prepared callback, so a preset replaces any callback you passed
before it — and vice versa. Pick one.

```php
$packager
    ->name('Blog')
    ->hasConfig()
    ->hasMigrations()
    ->hasAssets()
    ->hasQuickInstall();
```

## Customising the command

### A different name

```php
$packager
    ->name('Blog')
    ->hasInstallCommand()
    ->installCommandName('setup');   // php artisan blog:setup
```

The short name prefix stays; only the suffix changes.

### Hiding it

```php
$packager->hasInstallCommand()->installCommandHidden();
```

Runnable, but absent from `php artisan list`. Reasonable for a command that only exists to be called
by `installOnRun()`.

### Silencing progress

```php
$packager->hasInstallCommand(fn (InstallCommand $command) => $command->silent()->publishConfig());
```

Suppresses the "⚙️ Before Installation hooks…" lines. Per-step output is controlled by
`--no-interaction`.

### Turning it off

```php
$packager
    ->hasQuickInstall()
    ->when(app()->isProduction(), fn (Packager $p) => $p->withoutInstallCommand());
```

`withoutInstallCommand()` clears both the flag and the callback.

## Automatic installation

```php
$packager->hasQuickInstall()->installOnRun();
```

The command runs silently on `app.booted`, in the console only. Environment-scoped variants:

```php
$packager->installOnRunInLocal();
$packager->installOnRunInProduction();
$packager->installOnRunInEnvironment(['local', 'staging']);
```

:::danger `installOnRun()` runs on *every* console command
Not once — every time. `php artisan tinker`, `php artisan queue:work`, `php artisan test`: each one
boots the application, and each one triggers the installation. Publishing is idempotent without
`--force`, so the cost is filesystem checks rather than damage, but it is real, and any
after-installation hook you registered runs every time too.

Use it for a local development convenience, or not at all. It is not a substitute for documenting
`php artisan blog:install`.
:::

Note also that the environment variants evaluate `app()->environment()` **during `configure()`**,
inside `register()` — before the application has necessarily settled on its environment in every
context. `installOnRun()` with an explicit
[conditional](/conditional-configuration) is the more predictable form.

## Production safety

Run in production interactively and the command asks first:

```text
⚠️  You are in production environment. Are you sure you want to continue? (yes/no) [no]:
```

Declining exits successfully with `Installation cancelled.` — nothing is published. `--force` skips
the prompt and is also passed through to each `vendor:publish` call, overwriting existing files.

## Next steps output

After a successful interactive run the command prints follow-ups derived from what it published:

| Published | Message |
|---|---|
| `config`, and the file now exists | `Review configuration in config/blog.php` |
| `migrations` | `Run: php artisan migrate` |
| `seeders` | `Run: php artisan db:seed --class=<published seeder>` |

## A complete example

```php title="src/BlogServiceProvider.php"
use NyonCode\LaravelPackageToolkit\Commands\InstallCommand;

$packager
    ->name('Blog')
    ->hasConfig()
    ->hasMigrations()
    ->hasSeeders()
    ->hasViews()
    ->hasAssets()
    ->hasInstallCommand(function (InstallCommand $command) {
        $command
            ->beforeInstallation(function (InstallCommand $command) {
                $command->line('');
                $command->line('  <comment>Blog requires PHP 8.2 and Laravel 12.61+</comment>');
            })
            ->publishConfig()
            ->publishMigrations()
            ->publishAssets()
            ->publishForLocal('seeders')
            ->afterInstallation(function (InstallCommand $command) {
                if ($command->option('no-interaction')) {
                    return;
                }

                if ($command->confirm('Run migrations now?', true)) {
                    $command->call('migrate');
                }
            })
            ->askToStarRepoOnGitHub('https://github.com/acme/blog');
    });
```

## Testing

```php
test('the install command runs', function () {
    $this->artisan('blog:install', ['--no-interaction' => true])->assertSuccessful();
});

test('the install command publishes config, migrations and assets', function () {
    $this->artisan('blog:install', ['--no-interaction' => true, '--force' => true])
        ->assertExitCode(0)
        ->expectsOutputToContain('Publishing configuration')
        ->expectsOutputToContain('Publishing migrations')
        ->expectsOutputToContain('Publishing assets');
});

test('an unconfigured install command says so', function () {
    $this->artisan('blog:install', ['--no-interaction' => true])
        ->expectsOutputToContain('No installation steps configured');
});
```
