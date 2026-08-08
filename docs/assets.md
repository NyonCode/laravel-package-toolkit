---
title: Assets
description: Ship CSS and JavaScript, publish them under Laravel's conventional tag, and keep public/vendor in step automatically with the asset mirror.
---

# Assets

```php
public function hasAssets(string $directory = 'dist', bool $mirror = true): static
```

```php title="src/BlogServiceProvider.php"
$packager
    ->name('Blog')
    ->hasAssets();
```

```text
acme/blog/
└── dist/
    ├── css/blog.css
    └── js/blog.js
```

One call gives you three things: two publish tags, and a self-maintaining mirror that keeps
`public/vendor/blog` in step with `dist/` without anyone running a command.

## Publishing

```bash
php artisan vendor:publish --tag=blog::assets
php artisan vendor:publish --tag=laravel-assets --force
```

Both tags publish the same files to `public/vendor/{short-name}/`, preserving the directory
structure:

```text
public/vendor/blog/
├── css/blog.css
└── js/blog.js
```

The second tag is the interesting one. `laravel-assets` is what the Laravel application skeleton
already runs from Composer's `post-update-cmd`:

```json title="a Laravel application's composer.json"
"post-update-cmd": [
    "@php artisan vendor:publish --tag=laravel-assets --ansi --force"
]
```

It is the same hook Horizon, Telescope and Nova rely on, and it means **one command republishes
every installed package's assets after every `composer update`** — something a per-package tag
cannot express. Laravel accumulates publish groups per path, so registering both tags publishes the
same files and leaves an untagged `vendor:publish` unaffected.

## The asset mirror

Added in **2.3.0**. Publishing solves the deploy case. The mirror solves the case where nobody
published — which, for a package whose CSS is not optional, is the difference between working and
not.

`Support\PublishedAssets` is a container singleton shared by every package in the application. Ask
it for a URL and it returns one, mirroring the package's directory first if anything is missing or
out of date:

```php
use NyonCode\LaravelPackageToolkit\Support\PublishedAssets;

$url = app(PublishedAssets::class)->url(
    'blog',
    __DIR__.'/../dist/css/blog.css',
);

// https://example.test/vendor/blog/css/blog.css?id=1754640000
```

### Why files, not a route

Assets are served as real files under `public/`, never through a route, and that is a deliberate
constraint rather than a preference. Route delivery only works when the request reaches PHP, and a
very common nginx layout answers `.js` from a `try_files $uri =404` block that never forwards it —
the same block that 404s Livewire's own `/livewire/livewire.js`. On shared hosting that block is
frequently not the application's to change. A delivery mode that depends on it is not a delivery
mode; it is a support ticket. A file that exists is served by every web server configuration there
is.

### How the sync behaves

**Lazy.** Nothing is copied during `register()` or `boot()`. The first asset of a package to resolve
a URL *in a request* triggers the sync. A queue worker, an API route and an artisan command that
will never emit a `<script>` pay nothing.

**Incremental.** Each shipped file is compared against its published counterpart by mtime, and only
what is missing or older is copied. In steady state that is a handful of `stat` calls and no writes;
after an upgrade it is one copy per changed file, on one request.

**Complete.** The whole directory is walked, not just the file that was asked for. A code-split entry
point imports `./chunk-a1b2c3.js`, which the browser fetches directly and PHP is never asked to
resolve — a mirror driven only by resolved URLs would leave that chunk behind and break the bundle.

**Atomic.** Copies land through a temporary file and `rename()`. A request fetching a file while
another is mid-copy reads either the whole old one or the whole new one — never a truncated bundle,
which would fail as a syntax error and take everything in it down.

**Once per request per package.** The singleton memoises both the sync attempt and every resolved
URL.

### Cache busting

The returned URL carries `?id=<mtime of the published copy>`, and the sync sets that mtime to the
moment of the copy. That is what keeps Livewire's `data-navigate-track` meaningful: Livewire
full-page-reloads a `wire:navigate` visit when a tracked asset's query string changed, so an upgrade
is picked up instead of running new markup against a file the browser already cached.

```blade
<link rel="stylesheet" href="{{ $cssUrl }}" data-navigate-track="reload">
<script src="{{ $jsUrl }}" data-navigate-track="reload" defer></script>
```

### When `public/` is not writable

A read-only container, Vapor, a hardened deployment. Nothing throws:

```php
$assets = app(PublishedAssets::class);

$url = $assets->url('blog', $path);   // null if nothing is published

if ($url === null) {
    // Fall back to however you served it before — a CDN, an inline <style>.
}

if ($assets->isStale('blog', $path)) {
    logger()->warning('Blog assets are older than the installed package. Run vendor:publish.');
}
```

An older published copy is still preferred over nothing, and `isStale()` names that situation so you
can warn about it — a stale copy being served is a production condition worth surfacing, not a
silent one.

### Opting out

```php
$packager->hasAssets(mirror: false);
```

Keeps both publish tags, skips the mirror registration entirely. Use it when your deploy publishes
explicitly and you would rather the first request did no filesystem work at all.

### `flush()` — for long-lived workers

Added in **2.4.0**. The singleton is scoped to a request. Under a long-lived worker it outlives one,
and two things go wrong:

- the per-request sync marks limit the mirror to a **single attempt per worker lifetime**, so a
  published copy deleted underneath a running worker is never put back;
- every resolved URL keeps emitting the `?id=<mtime>` of the release the worker booted on — the
  exact query string Livewire watches to notice a deploy.

```php title="a consumer's AppServiceProvider"
use NyonCode\LaravelPackageToolkit\Support\PublishedAssets;

public function boot(): void
{
    $this->app->terminating(function () {
        if (app()->bound(PublishedAssets::class)) {
            app(PublishedAssets::class)->flush();
        }
    });
}
```

`flush()` clears the resolved URLs and the sync marks. It deliberately **keeps** the declared asset
directories: providers register those from `register()`, once per worker boot and not per request,
and clearing them would drop the resolver back to inferring a directory from the asset path — which
only works for a package whose asset directory happens to be named `dist`.

:::note Octane is not a supported target
The toolkit has never been developed against a long-lived worker. `flush()` exists because a
consumer committed to one, not because the toolkit targets them.
:::

## Serving assets from your package

The cleanest shape is a small helper that resolves both URLs once:

```php title="src/Blog.php"
namespace Acme\Blog;

use NyonCode\LaravelPackageToolkit\Support\PublishedAssets;

class Blog
{
    public static function styleUrl(): ?string
    {
        return app(PublishedAssets::class)->url('blog', __DIR__.'/../dist/css/blog.css');
    }

    public static function scriptUrl(): ?string
    {
        return app(PublishedAssets::class)->url('blog', __DIR__.'/../dist/js/blog.js');
    }

    public static function styleTag(): string
    {
        $url = static::styleUrl();

        return $url === null
            ? ''
            : sprintf('<link rel="stylesheet" href="%s" data-navigate-track="reload">', e($url));
    }
}
```

Then a Blade directive, registered from a [lifecycle hook](/lifecycle-hooks#bootedpackage):

```php
use Illuminate\Support\Facades\Blade;

$packager->bootedPackage(function () {
    Blade::directive('blogStyles', fn () => "<?php echo \\Acme\\Blog\\Blog::styleTag(); ?>");
});
```

```blade
<head>
    @blogStyles
</head>
```

## A custom directory

```php
$packager->hasAssets('assets');            // ../assets
$packager->hasAssets('public');            // ../public
$packager->hasAssets('resources/dist');    // ../resources/dist
```

The directory is resolved relative to your provider's directory, and must exist — a missing one
throws `DirectoryNotFoundException` at registration.

## Building assets

The toolkit does not build anything; ship the compiled output. A conventional setup:

```js title="vite.config.js"
import { defineConfig } from 'vite'

export default defineConfig({
  build: {
    outDir: 'dist',
    emptyOutDir: true,
    lib: {
      entry: { blog: 'resources/js/blog.js' },
      formats: ['es'],
    },
    rollupOptions: {
      output: {
        entryFileNames: 'js/[name].js',
        assetFileNames: 'css/[name][extname]',
      },
    },
  },
})
```

```json title="package.json"
{
    "scripts": {
        "build": "vite build"
    }
}
```

Commit `dist/`. A package consumer runs `composer require`, not `npm run build`.

:::tip Hashed filenames are unnecessary
The mirror already cache-busts with `?id=<mtime>`, and a hashed filename would defeat the mtime
comparison the incremental sync depends on. Keep the output names stable.
:::

## Introspection

```php
$packager->isAssetable();     // bool
$packager->assetDirectory();  // absolute path
$packager->mirrorsAssets();   // bool — false after hasAssets(mirror: false)
```

## Testing

```php
use NyonCode\LaravelPackageToolkit\Support\PublishedAssets;

test('resolving one asset mirrors the whole directory', function () {
    $shipped = __DIR__.'/../dist/css/blog.css';

    $url = app(PublishedAssets::class)->url('blog', $shipped);

    expect(public_path('vendor/blog/css/blog.css'))->toBeFile()
        ->and(public_path('vendor/blog/js/blog.js'))->toBeFile()
        ->and($url)->toBe(
            asset('vendor/blog/css/blog.css')
            .'?id='.filemtime(public_path('vendor/blog/css/blog.css'))
        );
});

test('a published copy newer than the shipped one is left alone', function () {
    $shipped = __DIR__.'/../dist/css/blog.css';
    $published = public_path('vendor/blog/css/blog.css');

    File::ensureDirectoryExists(dirname($published));
    File::put($published, '/* published by hand */');
    touch($published, filemtime($shipped) + 10);

    app(PublishedAssets::class)->url('blog', $shipped);

    expect(File::get($published))->toBe('/* published by hand */');
});
```

`public/` is shared between tests in a way the container is not, so clean up between cases:

```php
beforeEach(fn () => File::deleteDirectory(public_path('vendor/blog')));
```
