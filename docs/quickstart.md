---
title: Quickstart
description: Build a complete Laravel package — config, routes, migrations, views, an install command — from an empty directory.
---

# Quickstart

We are going to build `acme/blog`: a package with a config file, an API route, a migration, a Blade
view, a console command and an installer. Every step below is real code from a working package —
nothing is elided.

## 1. Scaffold the package

```bash
mkdir -p packages/blog && cd packages/blog
composer init --name=acme/blog --type=library --no-interaction
composer require nyoncode/laravel-package-toolkit
```

Set up autoloading and Laravel's package discovery:

```json title="packages/blog/composer.json"
{
    "name": "acme/blog",
    "autoload": {
        "psr-4": { "Acme\\Blog\\": "src/" }
    },
    "extra": {
        "laravel": {
            "providers": ["Acme\\Blog\\BlogServiceProvider"]
        }
    }
}
```

## 2. The provider

Start with the smallest thing that registers:

```php title="src/BlogServiceProvider.php"
namespace Acme\Blog;

use NyonCode\LaravelPackageToolkit\Contracts\Packable;
use NyonCode\LaravelPackageToolkit\PackageServiceProvider;
use NyonCode\LaravelPackageToolkit\Packager;

class BlogServiceProvider extends PackageServiceProvider implements Packable
{
    public function configure(Packager $packager): void
    {
        $packager->name('Blog');
    }
}
```

`name('Blog')` sets the human name — it is what `php artisan about` prints and what the install
command says it is installing. From it the toolkit derives the **short name** `blog`
(`Str::kebab()`), and that short name is what every tag and namespace in the rest of this page is
built from.

## 3. Config

Create the file:

```php title="config/blog.php"
return [
    'per_page' => 15,

    'cache' => [
        'enabled' => true,
        'ttl' => 3600,
    ],
];
```

And declare it:

```php title="src/BlogServiceProvider.php"
$packager
    ->name('Blog')
    ->hasConfig(); // [tl! focus]
```

That single call does three things. It merges `config/blog.php` into the application's config
under the key `blog`, so `config('blog.per_page')` works with no publishing at all. It registers the
file for publishing under `--tag=blog::config`. And it validates, at registration time, that the
file actually returns an array — a config file that forgets its `return` throws
`InvalidReturnTypeException` naming the file, instead of silently merging nothing.

```php
config('blog.per_page');       // 15
config('blog.cache.enabled');  // true
```

## 4. Routes

```php title="routes/api.php"
use Illuminate\Support\Facades\Route;

Route::middleware('api')->prefix('api/blog')->group(function () {
    Route::get('/posts', PostController::class);
});
```

```php title="src/BlogServiceProvider.php"
$packager
    ->name('Blog')
    ->hasConfig()
    ->hasRoutes(); // [tl! focus]
```

With no arguments, `hasRoutes()` discovers every file in the package's `routes` directory. The
toolkit deliberately does **not** wrap your routes in a group — prefixes, middleware and name
patterns stay yours to declare inside the file, where they are visible.

## 5. Migrations

```php title="database/migrations/create_blog_posts_table.php"
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_posts');
    }
};
```

Note the filename: no timestamp. The toolkit calls these *timeless* migrations, and prepends a
timestamp when the file is published, so the published copy sorts correctly against everything else
in the application. Shipping a fixed timestamp instead would date your migration to whenever *you*
wrote it, which is almost never where it belongs in a consumer's timeline.

```php title="src/BlogServiceProvider.php"
$packager
    ->name('Blog')
    ->hasConfig()
    ->hasRoutes()
    ->hasMigrations(); // [tl! focus]
```

## 6. Views

```blade title="resources/views/post.blade.php"
<article class="post">
    <h1>{{ $post->title }}</h1>
    {!! $post->body !!}
</article>
```

```php title="src/BlogServiceProvider.php"
$packager
    ->name('Blog')
    ->hasConfig()
    ->hasRoutes()
    ->hasMigrations()
    ->hasViews(); // [tl! focus]
```

Views register under the short name, so from anywhere in the application:

```php
return view('blog::post', ['post' => $post]);
```

A consumer who wants to change the markup runs `vendor:publish --tag=blog::views`, which copies the
directory to `resources/views/vendor/blog` — Laravel's own override location, checked before yours.

## 7. A console command

```php title="src/Commands/PruneCommand.php"
namespace Acme\Blog\Commands;

use Illuminate\Console\Command;

class PruneCommand extends Command
{
    protected $signature = 'blog:prune {--days=30}';

    protected $description = 'Delete blog posts in the trash';

    public function handle(): int
    {
        // …

        return self::SUCCESS;
    }
}
```

```php title="src/BlogServiceProvider.php"
$packager
    ->name('Blog')
    ->hasConfig()
    ->hasRoutes()
    ->hasMigrations()
    ->hasViews()
    ->hasCommands(); // [tl! focus]
```

With no arguments, `hasCommands()` scans `src/Commands` and resolves each file to a fully qualified
class name through Composer's PSR-4 map. Commands are only registered when the application is
running in the console, so a web request never pays for them.

## 8. The install command

```php title="src/BlogServiceProvider.php"
use NyonCode\LaravelPackageToolkit\Commands\InstallCommand; // [tl! focus]

$packager
    ->name('Blog')
    ->hasConfig()
    ->hasRoutes()
    ->hasMigrations()
    ->hasViews()
    ->hasCommands()
    ->hasInstallCommand(function (InstallCommand $command) { // [tl! focus:start]
        $command
            ->publishConfig()
            ->publishMigrations()
            ->publishViews()
            ->askToStarRepoOnGitHub('https://github.com/acme/blog');
    }); // [tl! focus:end]
```

Your users now get:

```bash
php artisan blog:install
```

```text
🚀 Installing Blog

(1/3) Publishing configuration...
  ✅ Published config
(2/3) Publishing migrations...
  ✅ Published migrations
(3/3) Publishing views...
  ✅ Published views

✨ Blog installed successfully!

📋 Next steps:
  • Review configuration in config/blog.php
  • Run: php artisan migrate
```

## The finished provider

```php title="src/BlogServiceProvider.php"
namespace Acme\Blog;

use NyonCode\LaravelPackageToolkit\Commands\InstallCommand;
use NyonCode\LaravelPackageToolkit\Contracts\Packable;
use NyonCode\LaravelPackageToolkit\PackageServiceProvider;
use NyonCode\LaravelPackageToolkit\Packager;

class BlogServiceProvider extends PackageServiceProvider implements Packable
{
    public function configure(Packager $packager): void
    {
        $packager
            ->name('Blog')
            ->hasConfig()
            ->hasRoutes()
            ->hasMigrations()
            ->hasViews()
            ->hasCommands()
            ->hasAbout()
            ->hasInstallCommand(function (InstallCommand $command) {
                $command
                    ->publishConfig()
                    ->publishMigrations()
                    ->publishViews()
                    ->askToStarRepoOnGitHub('https://github.com/acme/blog');
            });
    }
}
```

Twenty lines, and the package is complete: merged config, loaded routes, publishable migrations,
namespaced views, console commands, an `about` entry and an installer.

## Growing from here

The same chain extends to everything else the toolkit knows about. A more complete package might
look like this — the highlighted lines are what changed:

```php
$packager
    ->name('Blog')
    ->hasConfig()
    ->hasRoutes()
    ->hasBroadcastChannels(['channels.php'])          // [tl! ++]
    ->hasMigrations()
    ->hasSeeders()                                    // [tl! ++]
    ->hasFactories()                                  // [tl! ++]
    ->hasTranslations()                               // [tl! ++]
    ->hasViews()
    ->hasComponents('blog', [                         // [tl! ++:start]
        'card' => PostCard::class,
    ])
    ->hasAssets()                                     // [tl! ++:end]
    ->hasMiddlewareAliases(['blog.auth' => Authenticate::class]) // [tl! ++]
    ->hasEvent(PostPublished::class, NotifySubscribers::class)   // [tl! ++]
    ->hasCommands()
    ->hasFullInstall();                               // [tl! --]
```

Each of those has its own page:

- [Config](/config) · [Routes](/routes) · [Broadcast channels](/broadcast-channels)
- [Migrations](/migrations) · [Seeders](/seeders) · [Factories](/factories)
- [Translations](/translations) · [Views](/views) · [View components](/view-components)
- [Assets](/assets) · [Middleware](/middleware) · [Events](/events) · [Commands](/commands)
- [Publishing](/publishing) · [The install command](/install-command)
