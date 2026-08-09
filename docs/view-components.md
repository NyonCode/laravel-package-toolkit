---
title: View components
description: Register class-based Blade components individually, with aliases, or a whole namespace at a time.
---

# View components

Two mechanisms, for two different situations. Register components **individually** when you want
control over each tag name, or register a **namespace** when you would rather let Laravel resolve
them by convention.

```php
// Individually
public function hasComponent(string $prefix, string $componentClass, string $alias = ''): static
public function hasComponents(string $prefix, array|string $components): static

// By namespace
public function hasComponentNamespace(string $prefix, string $namespace): static
public function hasComponentNamespaces(array $namespaces): static
```

## Registering components individually

```php title="src/BlogServiceProvider.php"
use Acme\Blog\View\Components\PostCard;

$packager
    ->name('Blog')
    ->hasViews()
    ->hasComponent('blog', PostCard::class);
```

```blade
<x-blog-post-card :post="$post" />
```

The tag is `x-{prefix}-{kebab-case class name}`. The prefix is yours to choose and is not derived
from the short name, so a package can group components under more than one.

### With an alias

```php
$packager->hasComponent('blog', PostCard::class, 'card');
```

Registers **both** names — the alias does not replace the derived one:

```blade
<x-blog-card :post="$post" />        {{-- alias --}}
<x-blog-post-card :post="$post" />   {{-- still works --}}
```

That is worth knowing when you rename: adding an alias is always additive, so it is a safe way to
introduce a shorter tag without breaking anyone.

### Several at once

```php
use Acme\Blog\View\Components\{PostCard, PostList, AuthorBadge};

$packager->hasComponents('blog', [
    'card' => PostCard::class,
    'list' => PostList::class,
    AuthorBadge::class,          // no alias — derived name only [tl! ~~]
]);
```

```blade
<x-blog-card :post="$post" />
<x-blog-post-card :post="$post" />

<x-blog-list :posts="$posts" />
<x-blog-post-list :posts="$posts" />

<x-blog-author-badge :author="$author" />
```

String keys are aliases; numeric keys (a plain list) are not. Mixing both in one array is fine, as
above.

## Writing a component

```php title="src/View/Components/PostCard.php"
namespace Acme\Blog\View\Components;

use Acme\Blog\Models\Post;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class PostCard extends Component
{
    public function __construct(
        public Post $post,
        public bool $compact = false,
    ) {}

    public function excerpt(): string
    {
        return str($this->post->body)->stripTags()->words(30);
    }

    public function render(): View                          // [tl! focus:start]
    {
        return view('blog::components.post-card');
    }                                                       // [tl! focus:end]
}
```

```blade title="resources/views/components/post-card.blade.php"
<article {{ $attributes->merge(['class' => 'blog-post-card']) }}>
    <h3>{{ $post->title }}</h3>

    @unless ($compact)
        <p>{{ $this->excerpt() }}</p>
    @endunless

    <a href="{{ route('blog.posts.show', $post) }}">{{ __('Read more') }}</a>
</article>
```

The component's view lives in your package's view namespace, so `hasViews()` has to be declared as
well.

## Registering a namespace

For a package with many components, register the namespace once and let Laravel resolve classes on
demand:

```php
$packager
    ->name('Blog')
    ->hasViews()
    ->hasComponentNamespace('blog', 'Acme\\Blog\\View\\Components');
```

```blade
<x-blog::post-card :post="$post" />
<x-blog::author-badge :author="$author" />
<x-blog::admin.dashboard />
```

Note the syntax difference, which is Laravel's and not the toolkit's:

| Registration | Tag |
|---|---|
| `hasComponent('blog', PostCard::class)` | `<x-blog-post-card />` |
| `hasComponentNamespace('blog', '…\Components')` | `<x-blog::post-card />` |

The namespace form nests: a class at `View\Components\Admin\Dashboard` becomes
`<x-blog::admin.dashboard />`.

### Several namespaces

```php
$packager->hasComponentNamespaces([
    'blog' => 'Acme\\Blog\\View\\Components',
    'blog-admin' => 'Acme\\Blog\\View\\Components\\Admin',
]);
```

## Choosing between them

| | Individual | Namespace |
|---|---|---|
| Declaration cost | one line per component | one line total |
| Tag names | fully controlled, aliasable | convention only |
| A new component | needs registering | just works |
| Publishable | yes, per component directory | yes, whole namespace |

A package with five components that consumers use directly is better off registering them
individually — the tags are shorter and the aliases can be curated. A package with thirty is better
off with the namespace.

## Publishing

Both forms publish, under different tags:

```bash
php artisan vendor:publish --tag=blog::view-components
php artisan vendor:publish --tag=blog::view-component-namespaces
```

Destinations are built from the short name and the source directory's own name:

```text
app/View/Components/blog/Components/PostCard.php
```

For individual components, the source directory is the one holding the class file — derived by
reflection, so all components in the same directory publish together. For namespaces, the directory
is resolved through Composer's PSR-4 map.

:::warning A published component is a fork, not an override
Unlike views and translations, Laravel has no lookup order for component *classes*. A published copy
lives under the application's namespace and is not registered by anything — the consumer has to
register it themselves and, realistically, rename it to avoid confusion. Publishing components is a
"start from my code" feature, not a customisation hook.

If you want a genuine override point, put it in the component's **view** and let the consumer
publish that instead.
:::

:::warning Not covered by the install command
`publishComponents()` and `publishComponentNamespaces()` add their tags to the install command, and
`publishEverything()` includes them — but the installer only runs steps it has a definition for, and
these two have none. They are silently skipped. Publish them with `vendor:publish` directly, or from
an [install hook](/install-command#hooks):

```php
$command->afterInstallation(function (InstallCommand $command) {
    $command->call('vendor:publish', ['--tag' => 'blog::view-components']);
});
```
:::

## Introspection

```php
$packager->isViewComponentized();                // bool
$packager->viewComponents();                     // [['component' => …, 'alias' => …, 'prefix' => …], …]
$packager->viewComponentPaths();                 // unique directories holding component classes
$packager->isViewComponentNamespaceConfigured(); // bool
$packager->viewComponentNamespaces();            // ['prefix' => 'Namespace']
```

## Testing

```php
use Illuminate\Support\Facades\Blade;

test('the package registers its components', function () {
    expect(Blade::render('<x-blog-card :post="$post" />', ['post' => $post]))
        ->toContain($post->title);
});

test('both the alias and the derived name resolve', function () {
    expect(Blade::render('<x-blog-post-card :post="$post" />', ['post' => $post]))
        ->toContain($post->title);
});
```
