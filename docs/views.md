---
title: Views
description: Register your package's Blade views under a namespace and let consumers override them by publishing.
---

# Views

```php
public function hasViews(
    ?string $viewsPath = null,
    string $directory = '../resources/views',
    ?string $namespace = null,
): static
```

```php title="src/BlogServiceProvider.php"
$packager
    ->name('Blog')
    ->hasViews();
```

```text
acme/blog/
└── resources/views/
    ├── index.blade.php
    ├── post.blade.php
    └── partials/
        └── author.blade.php
```

Views register under the package short name, and — unlike the file-based resources — the whole
directory is registered, subdirectories included:

```php
view('blog::index');
view('blog::post', ['post' => $post]);
view('blog::partials.author', ['author' => $author]);
```

```blade
@include('blog::partials.author', ['author' => $post->author])

@extends('blog::layouts.app')
```

## The `$namespace` parameter

```php
$packager->hasViews(namespace: 'acme-blog'); // has no effect
```

:::warning Not currently applied
The third parameter is accepted and stored, but `bootViews()` registers the view namespace from
`shortName()` regardless. As of **2.4.0** there is no way to register views under a namespace other
than the package short name.

If you need a different one, set it with [`hasShortName()`](/packager#short-name) — which changes
the publish tags and every other namespaced path too — or call `loadViewsFrom()` yourself from a
[lifecycle hook](/lifecycle-hooks#bootingpackage).
:::

## A custom views directory

The first parameter takes a path, absolute or relative to your provider's directory:

```php
$packager->hasViews('../resources/blade');           // relative
$packager->hasViews(__DIR__.'/../resources/blade');  // absolute
```

Or use the second parameter, which is only consulted when the first is empty:

```php
$packager->hasViews(directory: '../ui/views');
```

Either way, a directory that does not exist throws `DirectoryNotFoundException` at registration. The
two parameters differ in one detail worth knowing: the first is checked with `is_dir()` and throws
if missing, the second is used as-is. Prefer the first for anything unusual.

## Publishing and overriding

```bash
php artisan vendor:publish --tag=blog::views
```

The directory is copied to `resources/views/vendor/{short-name}/`:

```text
resources/views/vendor/blog/
├── index.blade.php
├── post.blade.php
└── partials/author.blade.php
```

Laravel's view finder checks that directory **before** the package's own, per view. Which means a
consumer can publish everything and then delete all but the one file they wanted to change —
`blog::post` resolves to their copy, `blog::index` still resolves to yours, and your future updates
to `index.blade.php` keep arriving.

Worth putting in your readme, because the instinct is to keep the whole published directory and then
wonder why an upgrade changed nothing.

## Writing views a consumer will want to override

The more a view assumes, the harder it is to override well. A few habits that pay off:

**Keep layout out of content views.** A consumer with their own layout should not have to fork your
content view to use it.

```blade title="resources/views/post.blade.php"
@extends(config('blog.layout', 'blog::layouts.app'))

@section('content')
    <article>
        <h1>{{ $post->title }}</h1>
        {!! $post->body !!}
    </article>
@endsection
```

**Leave named slots for insertion**, so small additions do not require a fork:

```blade title="resources/views/post.blade.php"
<article>
    @stack('blog-post-before')

    <h1>{{ $post->title }}</h1>
    {!! $post->body !!}

    @stack('blog-post-after')
</article>
```

**Don't hard-code CSS classes** a consumer cannot change:

```blade
<article class="{{ config('blog.classes.post', 'blog-post') }}">
```

## Sharing data with your views

Three mechanisms, in increasing order of scope:

```php
// Passed per render — ordinary Blade.
view('blog::post', ['post' => $post]);

// Bound to specific views, resolved at render time.
$packager->hasViewComposer('blog::post', PostComposer::class);

// Available in every view in the application.
$packager->hasSharedDataForAllViews(['blogVersion' => '2.4.0']);
```

See [View composers](/view-composers) for both of the latter.

## Views and Blade components

Class-based components have their own view resolution, and register separately — see
[View components](/view-components):

```php
$packager
    ->name('Blog')
    ->hasViews()
    ->hasComponents('blog', PostCard::class);
```

A component's view is looked up in your namespace, so it belongs in the same directory:

```php title="src/View/Components/PostCard.php"
public function render(): View
{
    return view('blog::components.post-card');
}
```

## Anonymous components

Laravel finds anonymous components under a registered view namespace at
`components/`, so they work with nothing extra declared:

```text
resources/views/components/badge.blade.php
```

```blade
<x-blog::badge type="draft">Draft</x-blog::badge>
```

## Introspection

```php
$packager->isViewable(); // bool
$packager->views();      // absolute path to the views directory
```

## Testing

```php
test('the package registers its views', function () {
    expect(view()->exists('blog::post'))->toBeTrue();
});

test('a published view overrides the package view', function () {
    $this->artisan('vendor:publish --tag=blog::views')->assertExitCode(0);

    file_put_contents(resource_path('views/vendor/blog/post.blade.php'), 'overridden');

    expect(view('blog::post')->render())->toBe('overridden');
});
```
