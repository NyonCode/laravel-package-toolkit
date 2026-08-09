---
title: Migrations
description: Ship timestamped or timeless migrations, publish them in the right order, or run them without publishing at all.
---

# Migrations

```php
public function hasMigrations(
    ?array $migrationFiles = null,
    string $directory = '../database/migrations',
): static

public function canLoadMigrations(bool $value = true): static
```

Migrations are the one resource where *when* a file runs matters as much as what it contains, and
the toolkit spends most of its migration logic on getting that right.

:::note `hasMigrations()` takes an array, not a string
Unlike `hasConfig()` and `hasRoutes()`, the parameter is typed `?array`. Pass
`hasMigrations(['create_blog_posts_table.php'])` even for a single file.
:::

## Timestamped migrations

The familiar Laravel form — the file already carries its date:

```text
database/migrations/
├── 2025_01_15_120000_create_blog_posts_table.php
└── 2025_02_01_093000_add_slug_to_blog_posts_table.php
```

```php
$packager->name('Blog')->hasMigrations();
```

Published verbatim, filename unchanged:

```bash
php artisan vendor:publish --tag=blog::migrations
# → database/migrations/2025_01_15_120000_create_blog_posts_table.php
```

The catch is that those dates are *yours*. A consumer installing in 2027 gets migrations dated 2025,
which sort before every migration they have ever written — including the one that created their
`users` table, which your foreign key needs. It works when your tables stand alone, and breaks
quietly when they do not.

## Timeless migrations

Ship the file with no date prefix at all:

```text
database/migrations/
├── create_blog_posts_table.php
└── add_slug_to_blog_posts_table.php
```

```php
$packager->name('Blog')->hasMigrations();
```

The toolkit detects the missing prefix and generates one **at publish time**, from the current
moment, incrementing by one second per file in declaration order:

```bash
php artisan vendor:publish --tag=blog::migrations
# → database/migrations/2026_08_08_142530_create_blog_posts_table.php
# → database/migrations/2026_08_08_142531_add_slug_to_blog_posts_table.php
```

The consumer's own migrations all pre-date these, so a foreign key to their `users` table resolves.
And your ordering is preserved: `create_` before `add_slug_`, exactly as declared.

A prefix is recognised by this pattern, which is Laravel's own convention:

```php
public function hasDatePrefix(string $filename): bool
{
    return (bool) preg_match('/^\d{4}_\d{2}_\d{2}_\d{6}_/', $filename);
}
```

:::warning Order is declaration order
With discovery, the order is whatever the filesystem returns. If one timeless migration depends on
another — a foreign key, an added column — name the files explicitly so the generated timestamps
cannot come out reversed:

```php
$packager->hasMigrations([
    'create_blog_posts_table.php',
    'create_blog_comments_table.php',      // FK → blog_posts [tl! ~~]
    'add_slug_to_blog_posts_table.php',
]);
```
:::

### Writing a timeless migration

Nothing about the file changes — only the name:

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
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('body');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['published_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_posts');
    }
};
```

Anonymous-class migrations are the right choice for a package: no class name means no collision with
a consumer's migration of the same name.

## Mixing both styles

A package can ship both. Each file is judged on its own name — timestamped ones publish verbatim,
timeless ones get a generated prefix:

```text
database/migrations/
├── 2025_06_15_100000_create_blog_categories_table.php   → published as-is
└── create_blog_tags_table.php                           → gets today's timestamp
```

Useful during a transition, but a package that has to explain which of its migrations sort where is
harder to reason about than one that picks a style. Prefer timeless for new packages.

## Running migrations without publishing

Sometimes a package's schema is not the consumer's business — an internal queue table, a cache
table, a package that manages its own upgrades. `canLoadMigrations()` registers the migration paths
with the framework so `php artisan migrate` runs them straight from `vendor/`:

```php
$packager
    ->name('Blog')
    ->hasMigrations()
    ->canLoadMigrations();
```

```bash
php artisan migrate
# Runs the package's migrations from vendor/acme/blog/database/migrations
```

They stay publishable as well — the two are independent. If you want load-only, with no publishing
at all, there is no flag for that; skip `hasMigrations()` and call Laravel's
`loadMigrationsFrom()` yourself from a [lifecycle hook](/lifecycle-hooks).

| | Publishable | Runs from `vendor/` | Consumer can edit |
|---|---|---|---|
| `hasMigrations()` | yes | no | yes, after publishing |
| `hasMigrations()->canLoadMigrations()` | yes | yes | yes, after publishing |

:::warning Publishing *and* loading timeless migrations runs them twice
Laravel's migrator identifies a migration by its filename without the extension, and collapses
duplicates across paths. For **timestamped** migrations that is harmless: the published copy has the
same name as the source, so the consumer sees one migration either way.

For **timeless** migrations it is not. Publishing generates a new prefix, so
`create_blog_posts_table` and `2026_08_08_142530_create_blog_posts_table` are two different
migrations as far as the migrator is concerned, and `migrate` will run both — the second failing on
a table that already exists.

If you enable `canLoadMigrations()` on timeless migrations, tell consumers not to publish them, or
ship timestamped files instead.
:::

## Naming tables

Prefix your tables with the package name. It is the only defence against a collision with an
application table, and it makes a package's footprint obvious in a schema dump.

```php
Schema::create('posts', …);          // whose posts? [tl! --]
Schema::create('blog_posts', …);     // [tl! ++]
```

For a table name the consumer can change, read it from config:

```php title="config/blog.php"
'table_prefix' => 'blog_',
```

```php title="database/migrations/create_blog_posts_table.php"
Schema::create(config('blog.table_prefix').'posts', function (Blueprint $table) {
    // …
});
```

## Publishing

```bash
php artisan vendor:publish --tag=blog::migrations
```

```php
use NyonCode\LaravelPackageToolkit\Commands\InstallCommand;

$packager->hasInstallCommand(function (InstallCommand $command) {
    $command->publishConfig()->publishMigrations();
});
```

The install command adds `Run: php artisan migrate` to its closing "next steps" when migrations were
part of the run.

## Introspection

```php
$packager->isMigratable();              // bool
$packager->hasMigrationsOnRun;          // bool — public property, set by canLoadMigrations()
$packager->migrationFiles();            // Support\SplFileInfo[]
$packager->shouldPrependTimestamp();    // bool — true if any file lacks a date prefix
$packager->hasDatePrefix('create_x.php');  // false
$packager->getMigrationPublishMapping();   // [source => destination]
```

`getMigrationPublishMapping()` is what the publisher uses, and it is worth calling in a test to
assert your migrations land where you expect.
