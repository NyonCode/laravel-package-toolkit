---
title: Factories
description: Publish model factories into the application's factories directory — and why publishing is the only option Laravel leaves.
---

# Factories

```php
public function hasFactories(
    array|string|null $factoryFiles = null,
    string $directory = '../database/factories',
): static
```

Added in **2.4.0**. Like [seeders](/seeders), factories are **publish-only**.

```php title="src/BlogServiceProvider.php"
$packager
    ->name('Blog')
    ->hasFactories();
```

```bash
php artisan vendor:publish --tag=blog::factories
```

Files land flat in `database/factories/`, which is where the application's `Database\Factories`
namespace resolves:

```text
database/factories/BlogPostFactory.php
```

## Why there is no "load factories" option

Laravel removed `loadFactoriesFrom()` in version 8, when factories became classes resolved by
convention rather than files scanned from a directory. There is no framework hook left for a package
to say "my factories live here" — so the toolkit does not pretend to offer one.

If you want your factories usable **without** the consumer publishing them, point at them from the
model, which is the mechanism Laravel does still provide:

```php title="src/Models/Post.php"
namespace Acme\Blog\Models;

use Acme\Blog\Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasFactory;

    protected static function newFactory(): PostFactory // [tl! focus]
    {                                                   // [tl! focus]
        return PostFactory::new();                      // [tl! focus]
    }                                                   // [tl! focus]
}
```

```php title="src/Database/Factories/PostFactory.php"
namespace Acme\Blog\Database\Factories;

use Acme\Blog\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;

class PostFactory extends Factory
{
    protected $model = Post::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(),
            'slug' => $this->faker->unique()->slug(),
            'body' => $this->faker->paragraphs(3, true),
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => ['published_at' => now()]);
    }
}
```

Now `Post::factory()->published()->create()` works in the consumer's tests with nothing published,
because the factory lives under *your* PSR-4 prefix and the model resolves it directly.

The two approaches answer different questions, and a package can do both:

| | `hasFactories()` | `newFactory()` |
|---|---|---|
| Consumer must publish | yes | no |
| Consumer can edit the definition | yes | only by extending |
| Namespace | `Database\Factories` | your own |
| Useful for | seeding demo data, a starting point to customise | tests against your models |

## Writing a publishable factory

A factory intended for publishing declares the application's namespace:

```php title="database/factories/BlogPostFactory.php"
namespace Database\Factories;

use Acme\Blog\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;

class BlogPostFactory extends Factory
{
    protected $model = Post::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(),
            'slug' => fake()->unique()->slug(),
            'body' => fake()->paragraphs(4, true),
            'published_at' => fake()->boolean(70) ? fake()->dateTimeThisYear() : null,
        ];
    }
}
```

Prefix the class name. `database/factories/` belongs to the application, and `PostFactory` is a name
somebody else will want.

## Naming specific files

```php
$packager->hasFactories('BlogPostFactory.php');
$packager->hasFactories(['BlogPostFactory.php', 'BlogCommentFactory.php']);
$packager->hasFactories(directory: '../database/factory-stubs');
```

## In the install command

```php
use NyonCode\LaravelPackageToolkit\Commands\InstallCommand;

$packager->hasInstallCommand(function (InstallCommand $command) {
    $command
        ->publishMigrations()
        ->publishFactories()
        ->publishSeeders();
});
```

Factories are usually only wanted in development, which the installer can express:

```php
$packager->hasInstallCommand(function (InstallCommand $command) {
    $command
        ->publishConfig()
        ->publishMigrations()
        ->publishForLocal('factories', 'seeders'); // [tl! focus]
});
```

## Introspection

```php
$packager->isFactorable();  // bool
$packager->factoryFiles();  // Support\SplFileInfo[]
```
