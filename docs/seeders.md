---
title: Seeders
description: Publish database seeders flat into the application's seeders directory, so db:seed finds them without rewiring.
---

# Seeders

```php
public function hasSeeders(
    array|string|null $seederFiles = null,
    string $directory = '../database/seeders',
): static
```

Added in **2.4.0**. Seeders are a **publish-only** resource: the toolkit copies them into the
application, and nothing else. There is no boot step, because a seeder is only ever run explicitly.

```php title="src/BlogServiceProvider.php"
$packager
    ->name('Blog')
    ->hasSeeders();
```

```bash
php artisan vendor:publish --tag=blog::seeders
```

## Where they land, and why it matters

Published **flat** into `database/seeders/` — not into a `vendor/blog/` subdirectory:

```text
database/seeders/BlogPostSeeder.php
database/seeders/BlogCategorySeeder.php
```

That is the directory the application's own `Database\Seeders` namespace maps to, so a published
seeder is immediately runnable:

```bash
php artisan db:seed --class="Database\Seeders\BlogPostSeeder"
```

A `vendor/blog/` subdirectory would have needed the consumer to rewrite the namespace before the
class could be autoloaded, which is exactly the kind of chore that makes a "just run this" step stop
being one.

The flat layout has a cost, and it is yours to manage: **prefix your seeder class names**. Shipping
a `PostSeeder` into a directory the application already owns is asking for a collision.

## Writing the seeder

```php title="database/seeders/BlogPostSeeder.php"
namespace Database\Seeders;

use Acme\Blog\Models\Post;
use Illuminate\Database\Seeder;

class BlogPostSeeder extends Seeder
{
    public function run(): void
    {
        Post::factory()
            ->count(25)
            ->hasComments(3)
            ->create();
    }
}
```

The namespace must be `Database\Seeders` — that is where it will live once published.

:::warning Namespaces and the unpublished file
Because the file declares `Database\Seeders`, it is not autoloadable from inside your package (your
PSR-4 prefix maps `Acme\Blog\` to `src/`). That is fine and expected: a seeder only exists to be
published. If you want it usable before publishing, ship it under your own namespace and accept
that consumers must run it with the full class name.
:::

## Naming specific files

```php
$packager->hasSeeders('BlogPostSeeder.php');
$packager->hasSeeders(['BlogPostSeeder.php', 'BlogCategorySeeder.php']);
$packager->hasSeeders(directory: '../database/seed');
```

Discovery is not recursive, so a `database/seeders/demo/` subdirectory needs its own call.

## Shipping seeders as stubs

A `.stub` source publishes as `.php`:

```text
database/seeders/BlogDemoSeeder.stub   →   database/seeders/BlogDemoSeeder.php
```

The destination is built from `getBaseFileName()` — the name without its extension — plus `.php`,
which is the same convention [publishable providers](/providers) follow.

This is useful when the seeder contains placeholder content you do not want a static analyser or a
test runner in your own repository to pick up, or when it references classes that only exist after
installation.

## Registering with `DatabaseSeeder`

Publishing puts the file in place; it does not add it to the consumer's `DatabaseSeeder`. Tell them
what to add:

```php title="database/seeders/DatabaseSeeder.php"
public function run(): void
{
    $this->call([
        BlogCategorySeeder::class, // [tl! ++]
        BlogPostSeeder::class,     // [tl! ++]
    ]);
}
```

Or do it from an [install hook](/install-command#hooks), if your package is opinionated enough to
edit a consumer's file:

```php
use NyonCode\LaravelPackageToolkit\Commands\InstallCommand;

$packager->hasInstallCommand(function (InstallCommand $command) {
    $command
        ->publishMigrations()
        ->publishSeeders()
        ->afterInstallation(function (InstallCommand $command) {
            if ($command->confirm('Seed demo blog content now?', false)) {
                $command->call('db:seed', ['--class' => 'Database\Seeders\BlogPostSeeder']);
            }
        });
});
```

Prompting beats rewriting: `DatabaseSeeder` is a file consumers edit constantly, and a package that
patches it will eventually patch it wrongly.

## In the install command

```php
$packager->hasInstallCommand(function (InstallCommand $command) {
    $command->publishConfig()
        ->publishMigrations()
        ->publishSeeders();
});
```

`publishSeeders()` is its own step in the installer's progress output, and it adds
`Run: php artisan db:seed --class=<published seeder>` to the closing "next steps". It is also
included in `publishEverything()`.

## Introspection

```php
$packager->isSeedable();  // bool
$packager->seederFiles(); // Support\SplFileInfo[]
```

## Testing

```php
test('the package publishes its seeders', function () {
    $this->artisan('vendor:publish --tag=blog::seeders')->assertExitCode(0);

    expect(database_path('seeders/BlogPostSeeder.php'))->toBeFile()
        ->and(file_get_contents(database_path('seeders/BlogPostSeeder.php')))
        ->toContain('namespace Database\Seeders;')
        ->and(database_path('seeders/vendor/blog'))->not->toBeDirectory();
});
```
