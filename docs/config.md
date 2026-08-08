---
title: Config
description: Merge your package's configuration into the application and make it publishable.
---

# Config

```php
public function hasConfig(
    string|array|null $configFiles = null,
    string $directory = '../config',
): static
```

`hasConfig()` does two jobs at once. It merges each file into the application's config at
`register()` time, so your defaults are readable without anyone publishing anything, and it
registers the same files for publishing under `{short-name}::config`.

## Discovering every config file

```php title="src/BlogServiceProvider.php"
$packager
    ->name('Blog')
    ->hasConfig();
```

```text
acme/blog/
└── config/
    ├── blog.php
    └── blog-cache.php
```

Both files are merged. **The config key is the filename without its extension**, so:

```php
config('blog.per_page');
config('blog-cache.ttl');
```

## Naming files explicitly

```php
$packager->hasConfig('blog.php');
$packager->hasConfig(['blog.php', 'blog-cache.php']);
```

A file that does not exist throws `FileNotFoundException`:

```text
Config file [blog-cache.php] does not exist in directory [../config].
```

## A different directory

```php
$packager->hasConfig(directory: '../resources/configuration');
$packager->hasConfig(['blog.php'], '../src/config');
```

Relative paths resolve from the directory your provider lives in — see
[The Packager](/packager#paths).

## Writing the config file

A config file must `return` an array. The toolkit checks this during registration:

```php title="config/blog.php"
return [
    /*
    |--------------------------------------------------------------------------
    | Posts per page
    |--------------------------------------------------------------------------
    */
    'per_page' => env('BLOG_PER_PAGE', 15),

    'cache' => [
        'enabled' => env('BLOG_CACHE', true),
        'store' => env('BLOG_CACHE_STORE'),
        'ttl' => 3600,
    ],

    'models' => [
        'post' => \Acme\Blog\Models\Post::class,
    ],
];
```

Forget the `return` and registration fails with a message that names the file:

```text
Configuration file [blog] must return an array.
```

That check runs once, at `register()`, which means a broken config file fails on the first request
after installation rather than at the first `config()` call somewhere deep in a controller.

## How merging behaves

`mergeConfigFrom()` is a **shallow** merge, and that is Laravel's behaviour, not the toolkit's. If a
consumer publishes your config and your next release adds a key inside an existing nested array,
their published file wins for that whole array and your new key is invisible.

```php
// Your package ships:
'cache' => ['enabled' => true, 'ttl' => 3600, 'tags' => ['blog']],

// A consumer published an older copy containing:
'cache' => ['enabled' => false],

// config('blog.cache') is ['enabled' => false] — no ttl, no tags.
```

Two ways to live with it:

```php
// Read defensively for anything added after 1.0.
$ttl = config('blog.cache.ttl', 3600);
```

```php
// Or keep additions at the top level, where the shallow merge does reach them.
'cache_enabled' => true,
'cache_ttl' => 3600,
```

## Publishing

```bash
php artisan vendor:publish --tag=blog::config
```

Each file lands in `config/` under its own filename — `config/blog.php`, `config/blog-cache.php`.
Note that this is the *basename*, so two config files with the same name in different package
directories would collide; give them a package-specific prefix.

Add it to your [install command](/install-command):

```php
use NyonCode\LaravelPackageToolkit\Commands\InstallCommand;

$packager->hasInstallCommand(function (InstallCommand $command) {
    $command->publishConfig();
});
```

## Reading config from your package

Because the file is merged, your package can always read its own defaults — published or not:

```php
namespace Acme\Blog;

class PostRepository
{
    public function paginate(): LengthAwarePaginator
    {
        return Post::query()->paginate(config('blog.per_page'));
    }
}
```

For a value used across the package, a small accessor beats scattering string keys:

```php
namespace Acme\Blog;

class Blog
{
    public static function model(string $name): string
    {
        return config("blog.models.{$name}");
    }

    public static function cacheTtl(): int
    {
        return (int) config('blog.cache.ttl', 3600);
    }
}
```

## Multiple config files

A package large enough to warrant several files should still keep them recognisably one package:

```php
$packager->hasConfig(['blog.php', 'blog-cache.php', 'blog-search.php']);
```

```php
config('blog.per_page');
config('blog-cache.ttl');
config('blog-search.driver');
```

They all publish under the one `blog::config` tag — there is no per-file tag. If a consumer should
be able to publish them separately, ship one file and document its sections instead.

## Introspection

```php
$packager->isConfigurable();  // bool
$packager->configFiles();     // Support\SplFileInfo[]

foreach ($packager->configFiles() as $file) {
    $file->getBaseFileName(); // 'blog'      — the config key
    $file->getBasename();     // 'blog.php'  — the published filename
    $file->getPathname();     // absolute source path
}
```
