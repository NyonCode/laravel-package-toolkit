---
title: Testing
description: Test a package built with the toolkit using Orchestra Testbench and Pest — including the static-state reset that makes provider tests repeatable.
---

# Testing

A package built with the toolkit is tested the way any Laravel package is: inside a bootstrapped
application supplied by [Orchestra Testbench](https://github.com/orchestral/testbench). What is
specific to the toolkit is that most of what you want to assert happens during `register()` and
`boot()` — which means the provider has to be configured *before* the application boots, and any
static state it touched has to be cleaned up afterwards.

## Setup

```bash
composer require --dev orchestra/testbench pestphp/pest pestphp/pest-plugin-laravel
```

| Laravel | Testbench | Pest |
|---|---|---|
| 12.x | `^10.0` | `^3.1` |
| 13.x | `^11.0` | `^4.0` |

```php title="tests/TestCase.php"
namespace Acme\Blog\Tests;

use Acme\Blog\BlogServiceProvider;
use Illuminate\Foundation\Application;

class TestCase extends \Orchestra\Testbench\TestCase
{
    protected function getPackageProviders($app): array
    {
        return [BlogServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }
}
```

```php title="tests/Pest.php"
uses(Acme\Blog\Tests\TestCase::class)->in('Feature', 'Unit');
```

## Testing the resources

Once the provider is registered, everything it wired up is assertable through the framework's own
APIs:

```php
test('config is merged', function () {
    expect(config('blog.per_page'))->toBe(15);
});

test('views are registered', function () {
    expect(view()->exists('blog::post'))->toBeTrue();
});

test('translations resolve', function () {
    expect(trans('blog::messages.title'))->toBe('Blog');
});

test('routes are loaded', function () {
    $this->get('/api/blog/posts')->assertOk();
});

test('the command is registered', function () {
    $this->artisan('blog:prune', ['--dry-run' => true])->assertSuccessful();
});

test('the middleware alias exists', function () {
    expect(app(\Illuminate\Routing\Router::class)->getMiddleware())
        ->toHaveKey('blog.author');
});
```

## Testing publishing

```php
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;

test('the config group is registered', function () {
    expect(ServiceProvider::pathsToPublish(null, 'blog::config'))->not->toBeEmpty();
});

test('config publishes to the config directory', function () {
    $this->artisan('vendor:publish --tag=blog::config')->assertExitCode(0);

    expect(config_path('blog.php'))->toBeFile();
});

afterEach(function () {
    File::delete(config_path('blog.php'));
});
```

:::warning Publishing writes to a shared workbench
Testbench's skeleton application is on disk and shared by every test in the run. Publishing in one
test is visible to the next unless you clean up. Delete what you published in an `afterEach`, and
be specific about what you delete.
:::

## Varying the configuration per test

The awkward part of testing a package provider is that `configure()` is fixed at the class level,
while each test wants a different configuration. The pattern the toolkit uses on itself is a
provider whose `configure()` delegates to a static closure:

```php title="tests/TestServiceProvider.php"
namespace Acme\Blog\Tests;

use Closure;
use NyonCode\LaravelPackageToolkit\PackageServiceProvider;
use NyonCode\LaravelPackageToolkit\Packager;

class TestServiceProvider extends PackageServiceProvider
{
    public static ?Closure $configureUsing = null; // [tl! highlight]

    public static ?Closure $aboutDataUsing = null; // [tl! highlight]

    public function configure(Packager $packager): void
    {
        (self::$configureUsing ?? fn (Packager $packager) => null)($packager);
    }

    public function aboutData(): array
    {
        return (self::$aboutDataUsing ?? fn () => [])();
    }
}
```

```php title="tests/PackageTestCase.php"
abstract class PackageTestCase extends TestCase
{
    abstract protected function configure(Packager $packager): void;

    protected function setUp(): void
    {
        $this->resetServiceProviderState();

        TestServiceProvider::$configureUsing = fn (Packager $packager) => $this->configure($packager); // [tl! focus]
        TestServiceProvider::$aboutDataUsing = null;

        parent::setUp();   // ← the application boots here, with the closure in place [tl! focus]
    }

    protected function getPackageProviders($app): array
    {
        return [TestServiceProvider::class];
    }
}
```

The ordering is the whole trick: the closure is assigned **before** `parent::setUp()`, because that
is what boots the application and runs `register()`.

With Pest, each test file supplies its own configuration through a trait:

```php title="tests/Feature/ViewsTest.php"
trait ConfiguresViews
{
    public function configure(Packager $packager): void
    {
        $packager->name('Blog')->hasViews();
    }
}

uses(ConfiguresViews::class);

test('views are registered', function () {
    expect(view()->exists('blog::post'))->toBeTrue();
});
```

## Resetting static state

Laravel's `ServiceProvider` keeps its publish map, publish groups and optimize commands in **static**
properties, and `AboutCommand` keeps its sections the same way. None of that is reset between tests
in the same process, so the fifth test in a run sees the publish groups of the first four.

```php title="tests/PackageTestCase.php"
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Support\ServiceProvider;
use NyonCode\LaravelPackageToolkit\PackageServiceProvider;
use ReflectionClass;

protected function resetServiceProviderState(): void
{
    $this->resetStaticProperty(ServiceProvider::class, 'publishes', []);                             // [tl! focus:start]
    $this->resetStaticProperty(ServiceProvider::class, 'publishGroups', []);
    $this->resetStaticProperty(ServiceProvider::class, 'publishableMigrationPaths', []);
    $this->resetStaticProperty(ServiceProvider::class, 'optimizeCommands', []);
    $this->resetStaticProperty(ServiceProvider::class, 'optimizeClearCommands', []);
    $this->resetStaticProperty(PackageServiceProvider::class, 'isPackageAboutRegistered', false);

    AboutCommand::flushState();                                                                      // [tl! focus:end]
}

private function resetStaticProperty(string $class, string $property, mixed $value): void // [tl! collapse:start]
{
    $reflection = new ReflectionClass($class);

    // Not every property exists in every supported Laravel version.
    if (! $reflection->hasProperty($property)) {
        return;
    }

    $propertyReflection = $reflection->getProperty($property);

    if (! $propertyReflection->isStatic()) {
        return;
    }

    $propertyReflection->setValue(null, $value);
} // [tl! collapse:end]
```

The list is the part worth reading; `resetStaticProperty()` is folded above because it is the same
reflection boilerplate every package writes once. Expand it for the one detail that is not
boilerplate — the `hasProperty()` guard. These are framework internals, and they are not identical
across Laravel 12 and 13. Skipping a property that does not exist keeps the suite green on both.

`isPackageAboutRegistered` is the toolkit's own flag — it stops the "Laravel Package Toolkit"
section being registered more than once per process, which without a reset means only the first test
in a run sees it.

## Cleaning the filesystem

The asset mirror and `vendor:publish` both write into the workbench's `public/`, `config/` and
`database/` directories:

```php
protected function clear(): void
{
    File::deleteDirectory(public_path('vendor/blog'));

    File::delete(config_path('blog.php'));

    foreach (File::files(database_path('migrations')) as $migration) {
        if (str_contains($migration->getFilename(), 'blog')) {
            @unlink($migration->getPathname());
        }
    }
}
```

Timeless migrations need the fuzzy match: they are published with a generated timestamp prefix, so
the filename you clean up is not the filename you shipped.

## Testing the asset mirror

```php
use NyonCode\LaravelPackageToolkit\Support\PublishedAssets;

beforeEach(fn () => File::deleteDirectory(public_path('vendor/blog')));

test('resolving one asset mirrors the whole directory', function () {
    $shipped = __DIR__.'/../../dist/css/blog.css';

    $url = app(PublishedAssets::class)->url('blog', $shipped);

    expect(public_path('vendor/blog/css/blog.css'))->toBeFile()
        ->and(public_path('vendor/blog/js/blog.js'))->toBeFile()
        ->and($url)->toContain('?id=');
});

test('flush lets the mirror run again', function () {
    $shipped = __DIR__.'/../../dist/css/blog.css';

    app(PublishedAssets::class)->url('blog', $shipped);

    unlink(public_path('vendor/blog/css/blog.css'));
    clearstatcache();

    app(PublishedAssets::class)->flush();

    expect(app(PublishedAssets::class)->url('blog', $shipped))->not->toBeNull()
        ->and(public_path('vendor/blog/css/blog.css'))->toBeFile();
});
```

`clearstatcache()` is not optional here. PHP caches `stat` results within a request, and the mirror
decides what to copy from `filemtime()`.

## Testing the packager on its own

Some behaviour needs no application at all:

```php
use NyonCode\LaravelPackageToolkit\Packager;

test('the short name is derived from the name', function () {
    expect((new Packager)->name('My Awesome Package')->shortName())
        ->toBe('my-awesome-package');
});

test('an empty name is rejected', function () {
    expect(fn () => (new Packager)->name('  '))
        ->toThrow(InvalidArgumentException::class);
});

test('a non-kebab short name is rejected', function () {
    expect(fn () => (new Packager)->name('Blog')->hasShortName('My_Blog'))
        ->toThrow(InvalidArgumentException::class);
});

test('a timeless migration is detected', function () {
    $packager = new Packager;

    expect($packager->hasDatePrefix('create_posts_table.php'))->toBeFalse()
        ->and($packager->hasDatePrefix('2025_01_01_000000_create_posts_table.php'))->toBeTrue();
});
```

## Running migrations in tests

```php
protected function defineDatabaseMigrations(): void
{
    $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
}
```

Or, if your package calls `canLoadMigrations()`, they are already registered and
`RefreshDatabase` picks them up.

## CI

The toolkit's own matrix is a reasonable template — every supported PHP against every supported
Laravel, at both dependency extremes, on Linux and Windows:

```yaml title=".github/workflows/tests.yml"
strategy:
  matrix:
    os: [ubuntu-latest, windows-latest]
    php: ['8.2', '8.3', '8.4', '8.5']
    laravel: ['12.*', '13.*']
    stability: [prefer-lowest, prefer-stable]
    exclude:
      - laravel: '13.*'
        php: '8.2'
```

`prefer-lowest` is the one that earns its place: it is what catches a package that quietly depends
on a feature added in a patch release of a dependency it claims to support from `^12.0`.

Windows matters more than it looks for a package that resolves paths. Every path in the toolkit goes
through normalisation for that reason, and a package built on it should verify the same thing.
