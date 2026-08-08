---
title: Optimize commands
description: Hook your package's cache-warming and cache-clearing commands into php artisan optimize and optimize:clear.
---

# Optimize commands

```php
public function hasOptimizeCommands(
    ?string $optimize = null,
    ?string $clear = null,
    ?string $key = null,
): static
```

Laravel's `php artisan optimize` runs a list of cache-building commands, and `optimize:clear` runs
the matching teardown. Packages can add to both lists — that is what `ServiceProvider::optimizes()`
is for, and `hasOptimizeCommands()` is the packager's front end to it.

```php title="src/BlogServiceProvider.php"
$packager
    ->name('Blog')
    ->hasCommands()
    ->hasOptimizeCommands(
        optimize: 'blog:cache',
        clear: 'blog:clear',
    );
```

```bash
php artisan optimize
#  … caching the application
#  … blog:cache

php artisan optimize:clear
#  … clearing the application
#  … blog:clear
```

## One direction only

Both arguments are optional, and either alone is valid:

```php
// Something to build, nothing to tear down.
$packager->hasOptimizeCommands(optimize: 'blog:warm-search-index');

// Something to clear, nothing to build.
$packager->hasOptimizeCommands(clear: 'blog:flush-render-cache');
```

Passing neither is a no-op — the call returns `$this` and registers nothing, rather than throwing.

## Registering more than one pair

Every entry needs its own key. Laravel stores these commands in a map, and the toolkit defaults the
key to your package short name — so a second entry with no key overwrites the first:

```php
// ✗ only the second survives — both use the key 'blog'
$packager
    ->hasOptimizeCommands(optimize: 'blog:cache-routes')
    ->hasOptimizeCommands(optimize: 'blog:cache-search');

// ✓
$packager
    ->hasOptimizeCommands(optimize: 'blog:cache-routes', key: 'blog-routes')
    ->hasOptimizeCommands(optimize: 'blog:cache-search', key: 'blog-search');
```

The key is also what Laravel prints beside each step, so give it a name a reader will recognise.

## Writing the commands

```php title="src/Commands/CacheCommand.php"
namespace Acme\Blog\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CacheCommand extends Command
{
    protected $signature = 'blog:cache';

    protected $description = 'Cache the blog route map and search index';

    public function handle(): int
    {
        File::ensureDirectoryExists(dirname($this->path()));

        File::put($this->path(), '<?php return '.var_export($this->build(), true).';');

        $this->components->info('Blog cache built.');

        return self::SUCCESS;
    }

    private function path(): string
    {
        return base_path('bootstrap/cache/blog.php');
    }

    private function build(): array
    {
        return Post::published()->pluck('title', 'slug')->all();
    }
}
```

```php title="src/Commands/ClearCommand.php"
class ClearCommand extends Command
{
    protected $signature = 'blog:clear';

    protected $description = 'Clear the blog cache';

    public function handle(): int
    {
        File::delete(base_path('bootstrap/cache/blog.php'));

        $this->components->info('Blog cache cleared.');

        return self::SUCCESS;
    }
}
```

Register them like any other command, so they are runnable on their own as well:

```php
$packager
    ->hasCommands()
    ->hasOptimizeCommands(optimize: 'blog:cache', clear: 'blog:clear');
```

## Reading the cache

Guard the read so an application that never ran `optimize` still works:

```php title="src/Blog.php"
public static function slugMap(): array
{
    static $map = null;

    if ($map !== null) {
        return $map;
    }

    $cached = base_path('bootstrap/cache/blog.php');

    return $map = file_exists($cached)
        ? require $cached
        : Post::published()->pluck('title', 'slug')->all();
}
```

A cache your package *requires* is a cache that breaks a fresh clone. Always have the uncached path.

## Production only

Cache warming during local development is usually just latency:

```php
$packager
    ->name('Blog')
    ->whenProduction(function (Packager $packager) {
        $packager->hasOptimizeCommands(
            optimize: 'blog:cache',
            clear: 'blog:clear',
        );
    });
```

Note that this only affects registration. `php artisan optimize` in local would simply not run
`blog:cache` — the command still exists and can be run directly.

## Introspection

```php
$packager->isOptimizable();      // bool
$packager->optimizeCommands();   // [['optimize' => …, 'clear' => …, 'key' => …], …]
```

## Testing

```php
use Illuminate\Support\ServiceProvider;

test('the package registers its optimize commands', function () {
    expect(ServiceProvider::$optimizeCommands)->toHaveKey('blog')
        ->and(ServiceProvider::$optimizeCommands['blog'])->toBe('blog:cache')
        ->and(ServiceProvider::$optimizeClearCommands['blog'])->toBe('blog:clear');
});

test('an optimize-only entry registers no clear command', function () {
    expect(ServiceProvider::$optimizeCommands)->toHaveKey('blog-search')
        ->and(ServiceProvider::$optimizeClearCommands)->not->toHaveKey('blog-search');
});
```

Those static properties are shared process-wide, so reset them between tests — see
[Testing](/testing#resetting-static-state).
