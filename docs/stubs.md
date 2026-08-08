---
title: Stubs
description: Publish generator stubs into stubs/your-package so consumers can customise what your make: commands scaffold.
---

# Stubs

```php
public function hasStubs(
    array|string|null $stubFiles = null,
    string $directory = '../stubs',
): static
```

Added in **2.4.0**. Stubs are a **publish-only** resource. They exist so a consumer can change what
your generator commands produce, the same way `php artisan stub:publish` lets them change what the
framework's own generators produce.

```php title="src/BlogServiceProvider.php"
$packager
    ->name('Blog')
    ->hasStubs();
```

```bash
php artisan vendor:publish --tag=blog::stubs
```

## Where they land

Into `stubs/{short-name}/`, keeping the original extension:

```text
acme/blog/stubs/post-type.stub   →   stubs/blog/post-type.stub
acme/blog/stubs/widget.stub      →   stubs/blog/widget.stub
```

The subdirectory is not decoration. `stubs/` is a single flat directory shared with
`php artisan stub:publish` — which drops around thirty framework stubs straight into it — and with
every other installed package. Publishing `model.stub` into that directory would overwrite
Laravel's.

Extensions are preserved, unlike [seeders](/seeders) and [providers](/providers), where a `.stub`
source is published as `.php`. A stub is meant to stay a stub.

## Writing a stub

```text title="stubs/post-type.stub"
<?php

namespace {{ namespace }};

use Acme\Blog\Contracts\PostType;

class {{ class }} implements PostType
{
    public function label(): string
    {
        return '{{ label }}';
    }

    public function fields(): array
    {
        return [
            //
        ];
    }
}
```

The placeholder syntax is yours to choose — the toolkit only copies the file. Laravel's own
generators use `{{ namespace }}`, `{{ class }}`, `{{ rootNamespace }}`, and matching them makes your
stubs feel native.

## Using the stub from a generator command

The pattern is: prefer the published copy, fall back to the shipped one.

```php title="src/Commands/MakePostTypeCommand.php"
namespace Acme\Blog\Commands;

use Illuminate\Console\GeneratorCommand;

class MakePostTypeCommand extends GeneratorCommand
{
    protected $name = 'blog:make-post-type';

    protected $description = 'Create a new blog post type';

    protected $type = 'Post type';

    protected function getStub(): string
    {
        return $this->resolveStubPath('post-type.stub');
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\\Blog\\PostTypes';
    }

    /**
     * A published stub wins; otherwise use the one we ship.
     */
    protected function resolveStubPath(string $stub): string
    {
        $published = base_path("stubs/blog/{$stub}"); // [tl! focus]

        return file_exists($published)                // [tl! focus]
            ? $published                              // [tl! focus]
            : __DIR__."/../../stubs/{$stub}";         // [tl! focus]
    }
}
```

That check is the whole contract. Without it, publishing a stub does nothing and the feature is
decoration.

## Naming specific stubs

```php
$packager->hasStubs('post-type.stub');
$packager->hasStubs(['post-type.stub', 'widget.stub']);
$packager->hasStubs(directory: '../resources/stubs');
```

Discovery is not recursive, so a `stubs/admin/` subdirectory needs its own call — and note that both
calls publish into the same flat `stubs/blog/` destination, since the destination uses the basename.
Two stubs with the same basename in different source directories will collide.

## Local development only

Stubs are a development-time customisation, so there is a case for keeping them out of a production
install:

```php
$packager
    ->name('Blog')
    ->whenLocal(fn (Packager $p) => $p->hasStubs());
```

Or leave the declaration unconditional and let the installer decide:

```php
use NyonCode\LaravelPackageToolkit\Commands\InstallCommand;

$packager
    ->hasStubs()
    ->hasInstallCommand(function (InstallCommand $command) {
        $command->publishConfig()->publishForLocal('stubs');
    });
```

## In the install command

```php
$packager->hasInstallCommand(function (InstallCommand $command) {
    $command->publishConfig()->publishStubs();
});
```

`publishStubs()` is its own step in the installer's output and is included in `publishEverything()`.

## Introspection

```php
$packager->isStubbable(); // bool
$packager->stubFiles();   // Support\SplFileInfo[]
```

## Testing

```php
test('the package publishes its stubs into its own directory', function () {
    $this->artisan('vendor:publish --tag=blog::stubs')->assertExitCode(0);

    expect(base_path('stubs/blog/post-type.stub'))->toBeFile()
        ->and(base_path('stubs/post-type.stub'))->not->toBeFile()
        ->and(file_get_contents(base_path('stubs/blog/post-type.stub')))
        ->toContain('{{ class }}');
});

test('the generator prefers a published stub', function () {
    $this->artisan('vendor:publish --tag=blog::stubs')->assertExitCode(0);

    file_put_contents(base_path('stubs/blog/post-type.stub'), 'class {{ class }} {}');

    $this->artisan('blog:make-post-type', ['name' => 'Gallery'])->assertExitCode(0);

    expect(app_path('Blog/PostTypes/Gallery.php'))->toBeFile()
        ->and(file_get_contents(app_path('Blog/PostTypes/Gallery.php')))
        ->toBe('class Gallery {}');
});
```
