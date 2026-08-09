---
title: Routes
description: Load your package's route files and let consumers publish them for customisation.
---

# Routes

```php
public function hasRoutes(
    array|string|null $routeFiles = null,
    string $directory = '../routes',
): static
```

Route files are loaded at boot with Laravel's own `loadRoutesFrom()`, which means they participate
in `route:cache` exactly like an application's routes do.

## Loading every route file

```php title="src/BlogServiceProvider.php"
$packager
    ->name('Blog')
    ->hasRoutes();
```

```text
acme/blog/
└── routes/
    ├── web.php
    └── api.php
```

## Naming files explicitly

```php
$packager->hasRoutes('api.php');
$packager->hasRoutes(['api.php', 'web.php']);
```

The order is the load order. It rarely matters, but when two files register the same URI the first
one wins, so an explicit list is the way to make that deterministic.

:::warning Discovery picks up *every* file
`hasRoutes()` with no arguments loads everything in `routes/` — including a `channels.php` that is
meant for [broadcast channels](/broadcast-channels). A channel authorization callback loaded as a
route file is registered inside a route group, which is the wrong destination for it. Either name
your route files explicitly, or keep channels in their own directory.
:::

## Writing the route file

The toolkit loads the file and stops there. It does **not** apply a prefix, a middleware group or a
name prefix on your behalf — those belong inside the file, where a reader of your package can see
them:

```php title="routes/api.php"
use Acme\Blog\Http\Controllers\PostController;
use Illuminate\Support\Facades\Route;

Route::middleware(['api', 'throttle:60,1'])
    ->prefix('api/blog')
    ->name('blog.api.')
    ->group(function () {
        Route::get('/posts', [PostController::class, 'index'])->name('posts.index');
        Route::get('/posts/{post}', [PostController::class, 'show'])->name('posts.show');
    });
```

That gives `/api/blog/posts` and the route name `blog.api.posts.index`. Prefixing route names with
your short name is worth doing: route names are a flat global namespace, and `posts.index` is a
collision waiting to happen.

### Making the prefix configurable

```php title="config/blog.php"
return [
    'route' => [
        'prefix' => 'api/blog',
        'middleware' => ['api'],
    ],
];
```

```php title="routes/api.php"
Route::middleware(config('blog.route.middleware'))
    ->prefix(config('blog.route.prefix'))
    ->name('blog.')
    ->group(function () {
        Route::get('/posts', [PostController::class, 'index'])->name('posts.index');
    });
```

This works because config is merged during `register()` and routes are loaded during `boot()` — your
defaults are in place by the time the file runs, whether or not the consumer published it.

## Publishing

```bash
php artisan vendor:publish --tag=blog::routes
```

Files land in `routes/vendor/{short-name}/`:

```text
routes/vendor/blog/api.php
routes/vendor/blog/web.php
```

Publishing a route file is a **copy, not a takeover.** Your package keeps loading its own file from
`vendor/`; the published copy is inert until the consumer loads it themselves:

```php title="bootstrap/app.php"
->withRouting(
    web: __DIR__.'/../routes/web.php',
    then: function () {
        require base_path('routes/vendor/blog/api.php'); // [tl! focus]
    },
)
```

Which means a consumer who publishes and edits, without doing that, ends up with two copies of every
route — yours from the package, theirs from the published file, both registered. Say so in your
readme, or offer a config flag:

```php title="config/blog.php"
'load_routes' => true,
```

```php title="src/BlogServiceProvider.php"
$packager
    ->name('Blog')
    ->hasConfig()
    ->registeredPackage(function (Packager $packager) {
        if (config('blog.load_routes', true)) {
            $packager->hasRoutes();
        }
    });
```

## Route model binding

Register bindings from the [`booted` lifecycle hook](/lifecycle-hooks#bootedpackage), which runs
after routes are loaded:

```php
use Illuminate\Support\Facades\Route;

$packager->bootedPackage(function () {
    Route::bind('post', function (string $value) {
        return Post::where('slug', $value)->firstOrFail();
    });
});
```

## Controllers and middleware

Controllers are just classes in your package — nothing to declare. Middleware referenced by alias in
a route file must be registered first; see [Middleware](/middleware):

```php
$packager
    ->name('Blog')
    ->hasMiddlewareAliases(['blog.author' => EnsureUserIsAuthor::class])
    ->hasRoutes();
```

```php title="routes/api.php"
Route::middleware(['api', 'blog.author'])->group(/* … */);
```

`bootRoutes()` actually runs *before* `bootMiddleware()` in the toolkit's boot sequence, which
sounds like a problem and is not: Laravel resolves a middleware alias when a request is dispatched,
not when the route is declared. The alias only has to exist by the time a request arrives.

## Route caching

Because loading goes through `loadRoutesFrom()`, `php artisan route:cache` covers your package's
routes. The usual constraint applies to your files as much as an application's: **no closures**.

```php
// ✗ breaks route:cache
Route::get('/health', fn () => response()->json(['ok' => true]));

// ✓
Route::get('/health', HealthController::class);
```

An invokable single-action controller is the cheap fix.

## Introspection

```php
$packager->isRoutable();  // bool
$packager->routeFiles();  // Support\SplFileInfo[]
```
