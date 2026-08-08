---
title: Commands
description: Register your package's Artisan commands — discovered automatically, listed explicitly, or constructed by hand.
---

# Commands

```php
public function hasCommands(string|array|null $commandsClass = null, string $directory = 'Commands'): static
public function hasCommand(string $commandClass): static
```

Commands are registered only when the application is running in the console, so a web request never
constructs one.

## Automatic discovery

```php title="src/BlogServiceProvider.php"
$packager
    ->name('Blog')
    ->hasCommands();
```

```text
acme/blog/src/
├── BlogServiceProvider.php
└── Commands/
    ├── PruneCommand.php
    ├── ImportCommand.php
    └── ReindexCommand.php
```

All three are registered. Each file is resolved to a fully qualified class name by matching its path
against Composer's PSR-4 prefixes — the same map the autoloader uses — so the namespace does not
have to be guessed from a convention.

:::note The default directory has no `../`
`hasCommands()` defaults to `'Commands'`, resolved from your provider's directory — normally
`src/Commands`. Every other builder defaults to `'../something'`, because every other resource lives
outside `src/`. Commands are PHP classes, so they do not.
:::

Discovery is not recursive. A `src/Commands/Maintenance/` subdirectory needs its own call:

```php
$packager
    ->hasCommands()
    ->hasCommands(directory: 'Commands/Maintenance');
```

That works because `hasCommands()` **appends** rather than replaces.

## Listing commands explicitly

```php
use Acme\Blog\Commands\{ImportCommand, PruneCommand};

$packager->hasCommands([
    PruneCommand::class,
    ImportCommand::class,
]);
```

Or one at a time:

```php
$packager
    ->hasCommand(PruneCommand::class)
    ->hasCommand(ImportCommand::class);
```

`hasCommand()` is just `hasCommands()` with a single value. Both append, so mixing discovery with an
explicit addition is fine:

```php
$packager
    ->hasCommands()                                   // everything in src/Commands
    ->hasCommand(\Acme\Blog\Support\LegacyImport::class); // plus one from elsewhere
```

## A custom directory

```php
$packager->hasCommands(directory: 'Console/Commands');
$packager->hasCommands(directory: 'Artisan');
```

The directory is still resolved relative to your provider's directory, and must exist — a missing
one throws `DirectoryNotFoundException`.

## Commands that need constructor arguments

`hasCommands()` deals in class strings, which Laravel resolves through the container. That covers
constructor injection of anything the container can build:

```php title="src/Commands/ImportCommand.php"
namespace Acme\Blog\Commands;

use Acme\Blog\Import\Importer;
use Illuminate\Console\Command;

class ImportCommand extends Command
{
    protected $signature = 'blog:import {file : Path to the export file} {--dry-run}';

    protected $description = 'Import posts from an export file';

    public function __construct(private Importer $importer) // [tl! focus]
    {                                                       // [tl! focus]
        parent::__construct();                              // [tl! focus]
    }                                                       // [tl! focus]

    public function handle(): int
    {
        $count = $this->importer->import(
            $this->argument('file'),
            dryRun: $this->option('dry-run'),
        );

        $this->info(trans('blog::messages.imported', ['count' => $count]));

        return self::SUCCESS;
    }
}
```

For something the container cannot resolve on its own, override
[`packageCommands()`](/service-provider#packagecommands) on the provider and return an instance:

```php
public function packageCommands(): array
{
    return [
        new ImportCommand(new Importer(config('blog.import.batch_size'))),
    ];
}
```

Those are merged with whatever `hasCommands()` found.

## Writing a command

```php title="src/Commands/PruneCommand.php"
namespace Acme\Blog\Commands;

use Acme\Blog\Models\Post;
use Illuminate\Console\Command;

class PruneCommand extends Command
{
    protected $signature = 'blog:prune
                            {--days=30 : Delete posts trashed more than this many days ago}
                            {--dry-run : Report what would be deleted without deleting it}';

    protected $description = 'Permanently delete trashed blog posts';

    public function handle(): int
    {
        $cutoff = now()->subDays((int) $this->option('days'));

        $query = Post::onlyTrashed()->where('deleted_at', '<', $cutoff);
        $count = $query->count();

        if ($count === 0) {
            $this->components->info('Nothing to prune.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->components->warn("Would delete {$count} posts.");

            return self::SUCCESS;
        }

        if (! $this->confirm("Permanently delete {$count} posts?", false)) {
            return self::FAILURE;
        }

        $query->forceDelete();
        $this->components->info("Deleted {$count} posts.");

        return self::SUCCESS;
    }
}
```

**Prefix every command name with your short name.** `blog:prune`, not `prune`. Command names are a
flat global namespace, and a collision means one of the two commands simply never runs.

## Scheduling

There is no scheduling builder. Register from the
[`booted` lifecycle hook](/lifecycle-hooks#bootedpackage), wrapped in `app()->booted()` because the
scheduler is resolved after every provider has booted:

```php
use Illuminate\Console\Scheduling\Schedule;

$packager->bootedPackage(function () {
    app()->booted(function () {
        app(Schedule::class)
            ->command('blog:prune --days=90')
            ->daily()
            ->onOneServer();
    });
});
```

## Generator commands

A command that scaffolds files should publish its stubs so consumers can change what it generates —
see [Stubs](/stubs):

```php
$packager
    ->name('Blog')
    ->hasCommands()
    ->hasStubs();
```

```php title="src/Commands/MakePostTypeCommand.php"
protected function getStub(): string
{
    $published = base_path('stubs/blog/post-type.stub');

    return file_exists($published)
        ? $published
        : __DIR__.'/../../stubs/post-type.stub';
}
```

## The install command is separate

`php artisan blog:install` is not registered through `hasCommands()` — it has its own builder. See
[The install command](/install-command).

## Introspection

```php
$packager->isCommandable();  // bool
$packager->commands;         // string[] — public property
```

## Testing

```php
test('the command is registered', function () {
    expect(array_keys(app(\Illuminate\Contracts\Console\Kernel::class)->all()))
        ->toContain('blog:prune');
});

test('prune deletes trashed posts older than the cutoff', function () {
    $old = Post::factory()->trashed(at: now()->subDays(60))->create();
    $recent = Post::factory()->trashed(at: now()->subDay())->create();

    $this->artisan('blog:prune', ['--days' => 30])
        ->expectsConfirmation('Permanently delete 1 posts?', 'yes')
        ->assertExitCode(0);

    expect(Post::withTrashed()->find($old->id))->toBeNull()
        ->and(Post::withTrashed()->find($recent->id))->not->toBeNull();
});
```
