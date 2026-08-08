---
title: The about command
description: Add your package's version and diagnostic data to php artisan about.
---

# The about command

```php
public function hasAbout(bool $value = true): static
public function hasVersion(string $version): static
```

`php artisan about` is where a developer looks first when something is wrong. A package that
appears there — with its version and the two or three settings that actually determine its
behaviour — answers a support question before it is asked.

```php title="src/BlogServiceProvider.php"
$packager
    ->name('Blog')
    ->hasAbout();
```

```bash
php artisan about
```

```text
  Blog ..........................................................
  Version .................................................. 2.1.0
```

The section is titled with your package **name**, not the short name — this is the one place the
human-readable name is used for identity.

## Where the version comes from

With no `hasVersion()` call, the version is looked up at runtime:

1. Your package's `composer.json` is read for its `name` — `acme/blog`.
2. That name is passed to Composer's `InstalledVersions::getPrettyVersion()`.

So the version shown is the one Composer actually installed — `2.1.0`, `dev-main`, `1.0.x-dev` —
not a constant you have to remember to bump. If the lookup fails, the row is omitted rather than
showing a wrong value.

To override it:

```php
$packager->name('Blog')->hasAbout()->hasVersion('2.1.0-beta.3');
```

Useful for a package installed outside Composer, or one whose displayed version differs from its
package version.

## Custom rows

Override `aboutData()` on your provider:

```php title="src/BlogServiceProvider.php"
public function aboutData(): array
{
    return [
        'Driver' => fn () => config('blog.search.driver', 'database'),
        'Cache' => fn () => config('blog.cache.enabled') ? 'Enabled' : 'Disabled',
        'Posts' => fn () => (string) Post::count(),
        'Assets published' => fn () => is_dir(public_path('vendor/blog')) ? 'Yes' : 'No',
    ];
}
```

```text
  Blog ..........................................................
  Version .................................................. 2.1.0
  Driver .............................................. meilisearch
  Cache ................................................... Enabled
  Posts ....................................................... 142
  Assets published ............................................ Yes
```

**Use closures.** They are evaluated only when `about` actually runs, so a database query in an
`about` row does not become a database query on every request. A plain string is evaluated when
`configure()` runs, which for anything reading config or the database is too early to be correct
and too often to be free.

```php
'Posts' => (string) Post::count(),         // ✗ queries on every boot
'Posts' => fn () => (string) Post::count(), // ✓ queries only for `about`
```

Rows must be strings or closures returning strings. Return the empty string rather than `null` for
"unknown".

### Choosing what to show

The useful rows are the ones that differ between installations and change behaviour:

```php
public function aboutData(): array
{
    return [
        'Driver' => fn () => config('blog.search.driver'),
        'Queue' => fn () => config('blog.queue') ?: 'sync',
        'Config published' => fn () => file_exists(config_path('blog.php')) ? 'Yes' : 'No',
    ];
}
```

The last one is the kind of row that ends support threads.

## Setting data from the packager

`setAboutData()` on the packager does the same job as the provider method, and is available inside
`configure()`:

```php
$packager
    ->name('Blog')
    ->hasAbout()
    ->setAboutData([
        'Documentation' => 'https://acme.dev/blog',
    ]);
```

:::warning The provider method wins
`bootAboutCommand()` calls `setAboutData($this->aboutData())` before rendering, which **replaces**
anything set from `configure()`. If you use both, merge them in `aboutData()`:

```php
public function aboutData(): array
{
    return array_merge($this->packager->aboutData(), [
        'Posts' => fn () => (string) Post::count(),
    ]);
}
```

Simpler: pick one. `aboutData()` on the provider is the better default, because closures belong in a
class rather than in a configuration chain.
:::

## The toolkit's own section

Every application using the toolkit gets one extra section, registered once per process regardless
of how many packages are installed:

```text
  Laravel Package Toolkit ......................................
  Version .................................................. 2.4.0
```

It is not opt-in. It answers "which version of the toolkit is wiring all of this up", which matters
when three packages built on it behave inconsistently.

## Turning it off

```php
$packager->hasAbout(false);
```

`hasAbout()` defaults to `true`, so the explicit `false` is only needed to reverse an earlier call —
for example, from a [conditional](/conditional-configuration):

```php
$packager
    ->hasAbout()
    ->whenProduction(fn (Packager $p) => $p->hasAbout(false));
```

Whether that is worth doing is a judgement call. `about` is a local diagnostic command, and hiding a
package from it mostly costs you the next debugging session.

## Introspection

```php
$packager->isAboutable();   // bool
$packager->aboutData();     // array<string, string|Closure>
$packager->getVersion();    // string|null
$packager->version;         // string — public, empty unless hasVersion() was called
```

## Testing

```php
test('the package appears in about', function () {
    $this->artisan('about')
        ->expectsOutputToContain('Blog')
        ->assertExitCode(0);
});

test('about data is registered', function () {
    $this->artisan('about --only=blog')->assertExitCode(0);
});
```

:::note Static state between tests
`AboutCommand` accumulates sections in static state, and the toolkit tracks its own section with a
static flag. Reset both between tests — see [Testing](/testing#resetting-static-state).
:::
