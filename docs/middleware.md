---
title: Middleware
description: Register middleware aliases, push middleware into groups, or add it globally.
---

# Middleware

Three registration styles, matching the three things Laravel's router and kernel can do with
middleware.

```php
public function hasMiddlewareAliases(array $aliases): static
public function hasMiddlewareGroups(array $groups): static
public function hasMiddlewareGlobals(array $middlewares): static
```

## Aliases

An alias is a short name a route can refer to. It costs nothing until a route uses it, which makes
it the right default for a package.

```php title="src/BlogServiceProvider.php"
use Acme\Blog\Http\Middleware\{EnsureUserIsAuthor, VerifyBlogToken};

$packager
    ->name('Blog')
    ->hasMiddlewareAliases([
        'blog.author' => EnsureUserIsAuthor::class,
        'blog.token' => VerifyBlogToken::class,
    ]);
```

```php title="routes/api.php"
Route::middleware(['api', 'blog.token'])->group(function () {
    Route::post('/posts', [PostController::class, 'store'])->middleware('blog.author');
});
```

Consumers can use the alias too, on their own routes — which is the point.

**Prefix your aliases.** `auth` is taken; `blog.auth` is not. A package that registers a bare alias
will overwrite whatever the application had under that name, silently, and the failure surfaces
somewhere completely unrelated.

## Groups

Pushes middleware onto an existing group, or creates a new one:

```php
$packager->hasMiddlewareGroups([
    'web' => [TrackBlogVisit::class],
    'blog' => [
        VerifyBlogToken::class,
        EnsureUserIsAuthor::class,
    ],
]);
```

```php title="routes/web.php"
Route::middleware('blog')->group(function () {
    // …
});
```

The middleware is appended with `pushMiddlewareToGroup()`, so it runs **after** whatever the group
already contained.

Repeated calls accumulate per group rather than replacing it:

```php
$packager
    ->hasMiddlewareGroups(['blog' => [VerifyBlogToken::class]])
    ->hasMiddlewareGroups(['blog' => [EnsureUserIsAuthor::class]]);

// blog => [VerifyBlogToken, EnsureUserIsAuthor]
```

:::warning Pushing into `web` affects the whole application
Every request through the `web` group now runs your middleware — including pages that have nothing
to do with your package. Reach for it only when the behaviour genuinely is application-wide (request
tracking, a locale switch your package owns), and make it configurable when you can:

```php
$packager->when(
    config('blog.track_visits', false),
    fn (Packager $p) => $p->hasMiddlewareGroups(['web' => [TrackBlogVisit::class]]),
);
```
:::

## Global middleware

Pushed onto the HTTP kernel, so it runs on **every request**, before routing:

```php
$packager->hasMiddlewareGlobals([
    ForceJsonResponse::class,
]);
```

:::danger Almost never the right choice for a package
Global middleware runs on every request in the host application, including routes owned by other
packages and by the application itself. It cannot be excluded per route and cannot be turned off
without editing your package. Very few packages have a legitimate need for it — a security package
enforcing a header, perhaps.

If your middleware needs to run for your package's routes, put it in your route file. If it needs to
run for the application's routes, push it into a group and let the consumer decide.
:::

## Writing middleware

```php title="src/Http/Middleware/EnsureUserIsAuthor.php"
namespace Acme\Blog\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAuthor
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isBlogAuthor(), 403, __('blog::messages.not_an_author'));

        return $next($request);
    }
}
```

### With parameters

```php title="src/Http/Middleware/VerifyBlogToken.php"
public function handle(Request $request, Closure $next, string $ability = 'read'): Response
{
    abort_unless($request->user()?->tokenCan("blog:{$ability}"), 403);

    return $next($request);
}
```

```php
Route::middleware('blog.token:write')->post('/posts', …);
```

## Making registration configurable

A consumer who wants to swap your middleware for their own should not have to fork the package:

```php title="config/blog.php"
return [
    'middleware' => [
        'author' => \Acme\Blog\Http\Middleware\EnsureUserIsAuthor::class,
    ],
];
```

```php title="src/BlogServiceProvider.php"
$packager
    ->name('Blog')
    ->hasConfig()
    ->registeredPackage(function (Packager $packager) {
        $packager->hasMiddlewareAliases([
            'blog.author' => config('blog.middleware.author'),
        ]);
    });
```

The `registeredPackage` hook runs after your config is merged, so the value is available — but note
that it also runs *after* `register()` has already done its work, and middleware is booted later, so
the alias still lands in time.

## Ordering

The toolkit registers aliases, then groups, then globals — in that order, all inside
`bootMiddleware()`. It does not attempt to control Laravel's own middleware priority. If your
middleware must run before or after a framework one, the consumer sets that in their
`bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->priority([
        \Illuminate\Session\Middleware\StartSession::class,
        \Acme\Blog\Http\Middleware\TrackBlogVisit::class, // [tl! ++]
        \Illuminate\Routing\Middleware\SubstituteBindings::class,
    ]);
})
```

Document the requirement rather than trying to enforce it.

## Introspection

```php
$packager->isSetMiddlewareAliases();  // bool
$packager->getMiddlewareAliases();    // ['alias' => Middleware::class]
$packager->isSetMiddlewareGroups();   // bool
$packager->getMiddlewareGroups();     // ['group' => [Middleware::class, …]]
$packager->isSetMiddlewareGlobals();  // bool
$packager->getMiddlewareGlobals();    // [Middleware::class, …]
```

## Testing

```php
use Illuminate\Routing\Router;

test('the package registers its middleware alias', function () {
    expect(app(Router::class)->getMiddleware())
        ->toHaveKey('blog.author');
});

test('the package pushes middleware into its group', function () {
    expect(app(Router::class)->getMiddlewareGroups()['blog'])
        ->toContain(VerifyBlogToken::class);
});
```
