---
title: View composers & shared data
description: Bind data to specific views when they render, or share a value with every view in the application.
---

# View composers & shared data

Two ways to get data into a view without threading it through every controller that renders one. A
**composer** runs when a specific view is rendered; **shared data** is available to every view in
the application, always.

```php
public function hasViewComposer(string|array $views, string|Closure $composer): static
public function hasSharedDataForAllViews(array $viewSharedData): static
```

## View composers

```php title="src/BlogServiceProvider.php"
use Acme\Blog\View\Composers\SidebarComposer;

$packager
    ->name('Blog')
    ->hasViews()
    ->hasViewComposer('blog::sidebar', SidebarComposer::class);
```

```php title="src/View/Composers/SidebarComposer.php"
namespace Acme\Blog\View\Composers;

use Acme\Blog\Repositories\PostRepository;
use Acme\Blog\Models\Category;
use Illuminate\View\View;

class SidebarComposer
{
    public function __construct(private PostRepository $posts) {}

    public function compose(View $view): void
    {
        $view->with('categories', Category::withCount('posts')->get());
        $view->with('recent', $this->posts->latest(limit: 5));
    }
}
```

```blade title="resources/views/sidebar.blade.php"
<aside>
    @foreach ($categories as $category)
        <a href="{{ route('blog.category', $category) }}">
            {{ $category->name }} ({{ $category->posts_count }})
        </a>
    @endforeach
</aside>
```

The composer class is resolved from the container, so constructor injection works.

### Closures

For something small, skip the class:

```php
$packager->hasViewComposer('blog::sidebar', function (View $view) {
    $view->with('categories', Category::all());
});
```

:::warning Closures and `config:cache`
A closure composer is registered at boot and lives in memory, so it is unaffected by config caching.
But a closure captured in a property of a serialisable object is not — keep composer closures inside
`configure()` and they are fine.
:::

### Several views at once

```php
$packager->hasViewComposer(
    ['blog::sidebar', 'blog::footer', 'blog::partials.nav'],
    SidebarComposer::class,
);
```

### Wildcards

Laravel's own wildcard syntax works, since the value is passed straight to `View::composer()`:

```php
$packager->hasViewComposer('blog::*', BlogComposer::class);          // every package view
$packager->hasViewComposer('blog::admin.*', AdminComposer::class);   // one subdirectory
$packager->hasViewComposer('*', GlobalComposer::class);              // every view — see below
```

:::danger `'*'` runs for every view in the application
Including views that have nothing to do with your package. A package that registers a global
composer imposes its cost on every render in the host application. Scope it to your own namespace
unless you have a specific reason not to.
:::

### Registering more than one

Composers accumulate per view, keyed by the view name — so registering two composers for the same
view keeps only the last:

```php
$packager
    ->hasViewComposer('blog::sidebar', CategoryComposer::class)
    ->hasViewComposer('blog::sidebar', RecentPostsComposer::class);

// Only RecentPostsComposer is registered for blog::sidebar.
```

Different views accumulate normally:

```php
$packager
    ->hasViewComposer('blog::sidebar', SidebarComposer::class)   // ✓
    ->hasViewComposer('blog::footer', FooterComposer::class);    // ✓
```

If one view genuinely needs two composers, combine them into one class, or call
`View::composer()` directly from a [lifecycle hook](/lifecycle-hooks#bootedpackage).

## Shared data

```php
$packager
    ->name('Blog')
    ->hasSharedDataForAllViews([
        'blogName' => 'Acme Blog',
        'blogVersion' => '2.4.0',
        'blogSettings' => ['theme' => 'default', 'rtl' => false],
    ]);
```

```blade
{{-- available in every view in the application --}}
<footer>{{ $blogName }} v{{ $blogVersion }}</footer>
```

### Allowed values

Keys must be strings. Values must be scalar, array, `null`, or an instance of
`Illuminate\Contracts\Support\Arrayable`. Anything else throws `InvalidArgumentException` during
registration:

```php
$packager->hasSharedDataForAllViews([
    'blogName' => 'Acme Blog',                    // ✓ string
    'blogPerPage' => 15,                          // ✓ int
    'blogFlags' => ['beta' => true],              // ✓ array
    'blogTheme' => null,                          // ✓ null
    'blogCategories' => Category::all(),          // ✓ Arrayable
    'blogFormatter' => fn () => …,                // ✗ Closure
    'blogRepository' => new PostRepository(),     // ✗ object
]);
```

The restriction exists to stop packages sharing service objects into the view layer — a habit that
turns templates into an untestable second application.

:::warning Eloquent queries run at boot
`Category::all()` in the array above executes during `boot()`, on **every request**, whether or not
any view uses it. That is a database query added to your artisan commands, your queue workers and
your health check. Use a [view composer](#view-composers) instead — it runs only when the view that
needs the data is actually rendered.
:::

### Merging

Calls accumulate, and a repeated key wins on the later call:

```php
$packager
    ->hasSharedDataForAllViews(['blogName' => 'Blog'])
    ->hasSharedDataForAllViews(['blogVersion' => '2.4.0', 'blogName' => 'Acme Blog']);

// ['blogName' => 'Acme Blog', 'blogVersion' => '2.4.0']
```

### Prefix your keys

Shared data is a flat global namespace shared with the application and every other package. A
package that shares `$settings` will one day silently overwrite something.

```php
'blogSettings' => …   // ✓
'settings' => …       // ✗
```

## Choosing between them

| | Composer | Shared data |
|---|---|---|
| Runs | when a matching view renders | at boot, always |
| Scope | the views you name | every view |
| Suitable for | queries, request-dependent values | constants, static config |
| Cost when unused | none | paid on every request |

The rule of thumb: if computing the value costs anything at all, it belongs in a composer.

## Introspection

```php
$packager->isViewComposable();  // bool
$packager->viewComposers();     // ['view name' => composer]
$packager->isSharedWithViews(); // bool
$packager->viewSharedData();    // ['key' => value]
```

## Testing

```php
test('the composer binds categories to the sidebar', function () {
    Category::factory()->count(3)->create();

    expect(view('blog::sidebar')->render())->toContain('Announcements');
});

test('shared data reaches an application view', function () {
    expect(Blade::render('{{ $blogName }}'))->toBe('Acme Blog');
});
```
