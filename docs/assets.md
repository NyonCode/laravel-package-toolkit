---
title: Assets
description: Ship CSS and JavaScript, render them from a template with one directive, and let the consuming application's Vite build take over when it wants to.
---

# Assets

```php
public function hasAssets(string $directory = 'dist', bool $mirror = true, array $entries = []): static
public function hasViteAssets(array $entries, ?string $base = null): static
```

```php title="src/BlogServiceProvider.php"
$packager
    ->name('Blog')
    ->hasAssets(entries: [
        'css/blog.css',
        'js/blog.js',
    ]);
```

```text
acme/blog/
└── dist/
    ├── css/blog.css
    └── js/blog.js
```

```blade title="any layout — the package's own, or the application's"
<head>
    @packageStyles('blog')
</head>
<body>
    @packageScripts('blog')
</body>
```

One call gives you four things: two publish tags, a self-maintaining mirror that keeps
`public/vendor/blog` in step with `dist/` without anyone running a command, and tags a template
can ask for by name. [`hasViteAssets()`](#vite-in-the-application) adds the fifth — the same
declaration resolving through the consuming application's own Vite build when that application
wants the package inside it.

## Publishing

```bash
php artisan vendor:publish --tag=blog::assets
php artisan vendor:publish --tag=laravel-assets --force
```

Both tags publish the same files to `public/vendor/{short-name}/`, preserving the directory
structure:

```text
public/vendor/blog/
├── css/blog.css
└── js/blog.js
```

The second tag is the interesting one. `laravel-assets` is what the Laravel application skeleton
already runs from Composer's `post-update-cmd`:

```json title="a Laravel application's composer.json"
"post-update-cmd": [
    "@php artisan vendor:publish --tag=laravel-assets --ansi --force"
]
```

It is the same hook Horizon, Telescope and Nova rely on, and it means **one command republishes
every installed package's assets after every `composer update`** — something a per-package tag
cannot express. Laravel accumulates publish groups per path, so registering both tags publishes the
same files and leaves an untagged `vendor:publish` unaffected.

## The asset mirror

Added in 2.3.0, which has since been withdrawn — in practice, available from **2.4**. Publishing
solves the deploy case. The mirror solves the case where nobody published — which, for a package
whose CSS is not optional, is the difference between working and not.

`Support\PublishedAssets` is a container singleton shared by every package in the application. Ask
it for a URL and it returns one, mirroring the package's directory first if anything is missing or
out of date:

```php
use NyonCode\LaravelPackageToolkit\Support\PublishedAssets;

// An og:image the layout composes itself — not a stylesheet or a script, so not
// something a directive renders, and the mirror is how it reaches `public/`.
$url = app(PublishedAssets::class)->url(
    'blog',
    __DIR__.'/../dist/img/og.png',
);

// https://example.test/vendor/blog/img/og.png?id=1754640000
```

:::note That example is deliberately not a stylesheet
This is the layer, not the way to reach it. A file a template renders as a `<link>` or a `<script>`
belongs in [`hasAssets(entries: [...])`](#rendering-them-in-a-template) and is asked for by its key —
`@packageAssetUrl('blog', 'css/blog.css')`, or `app(PackageAssets::class)->url('blog', 'css/blog.css')`.
That resolves the same mirror underneath and additionally goes through the
[application's Vite build](#vite-in-the-application) when that build covers the entry.

`PublishedAssets` directly is for what a directive cannot render: an image or font the template
composes itself, an asset registered by an application rather than a package, and `isStale()`, which
has no counterpart on `PackageAssets`. Building a tag around it is
[a pattern with no remaining use](#what-the-hand-written-tag-misses).
:::

### Why files, not a route

Assets are served as real files under `public/`, never through a route, and that is a deliberate
constraint rather than a preference. Route delivery only works when the request reaches PHP, and a
very common nginx layout answers `.js` from a `try_files $uri =404` block that never forwards it —
the same block that 404s Livewire's own `/livewire/livewire.js`. On shared hosting that block is
frequently not the application's to change. A delivery mode that depends on it is not a delivery
mode; it is a support ticket. A file that exists is served by every web server configuration there
is.

### How the sync behaves

**Lazy.** Nothing is copied during `register()` or `boot()`. The first asset of a package to resolve
a URL *in a request* triggers the sync. A queue worker, an API route and an artisan command that
will never emit a `<script>` pay nothing.

**Incremental.** Each shipped file is compared against its published counterpart by mtime, and only
what is missing or older is copied. In steady state that is a handful of `stat` calls and no writes;
after an upgrade it is one copy per changed file, on one request.

**Complete.** The whole directory is walked, not just the file that was asked for. A code-split entry
point imports `./chunk-a1b2c3.js`, which the browser fetches directly and PHP is never asked to
resolve — a mirror driven only by resolved URLs would leave that chunk behind and break the bundle.

**Atomic.** Copies land through a temporary file and `rename()`. A request fetching a file while
another is mid-copy reads either the whole old one or the whole new one — never a truncated bundle,
which would fail as a syntax error and take everything in it down.

**Once per request per package.** The singleton memoises both the sync attempt and every resolved
URL.

### Cache busting

The returned URL carries `?id=<mtime of the published copy>`, and the sync sets that mtime to the
moment of the copy. That is what keeps Livewire's `data-navigate-track` meaningful: Livewire
full-page-reloads a `wire:navigate` visit when a tracked asset's query string changed, so an upgrade
is picked up instead of running new markup against a file the browser already cached.

`@packageStyles` and `@packageScripts` put `data-navigate-track="reload"` on every tag they render
for that reason. Writing the tags by hand, it is on you:

```blade
<link rel="stylesheet" href="{{ $cssUrl }}" data-navigate-track="reload">
<script src="{{ $jsUrl }}" data-navigate-track="reload" defer></script>
```

### When `public/` is not writable

A read-only container, Vapor, a hardened deployment. Nothing throws:

```php
$assets = app(PublishedAssets::class);

$url = $assets->url('blog', $path);   // null if nothing is published

if ($url === null) {
    // Fall back to however you served it before — a CDN, an inline <style>.
}

if ($assets->isStale('blog', $path)) {
    logger()->warning('Blog assets are older than the installed package. Run vendor:publish.');
}
```

An older published copy is still preferred over nothing, and `isStale()` names that situation so you
can warn about it — a stale copy being served is a production condition worth surfacing, not a
silent one.

### Keeping the tag: `hasAssetFallback()`

Added in **2.4.2**. The code above is for markup you compose yourself. For a *declared entry* the
renderer has the same problem and one more constraint: there is nothing to point the tag at, so
`@packageAssets` drops it. That is right for an entry the application chose not to build, and wrong
for the entry that is your package's only copy — the page loses its stylesheet or its behaviour with
nothing in the markup, the log or the console to say why, on exactly the deployments least likely to
go looking.

If your package also serves its assets from a route of its own, say so and the tag survives:

```php
$packager
    ->hasAssets(entries: ['js/blog.js'])
    ->hasAssetFallback(fn (string $file): string => route('blog.asset', ['file' => $file]));
```

The resolver is handed the entry's path inside the asset directory and the package's short name, and
is reached only after both the mirror and `public/vendor/{short-name}` came back with nothing — in a
normal deployment it is never called at all. Return `null` and the tag is dropped as before.

It owns the whole URL it returns, **cache-busting query string included**. The `?id=` the renderer
appends elsewhere is the published copy's mtime, and the point of being here is that there is no
published copy; only you know what your route varies on.

What you get back for declaring it is the tag itself — `type="module"` or the `defer` that
`classic()` implies, your declared attributes, `data-navigate-track="reload"` and the application's
CSP nonce. That is the whole reason to declare a fallback rather than hand-write a `<script>` beside
the directive, which is the mistake `@packageAssets` exists to remove.

`resolution()` reports such an entry as `fallback`, so a deployment serving from the route is
distinguishable from one serving nothing.

:::note A fallback is not a substitute for publishing
It is the same file, served the slow way — through PHP, past the middleware stack, with no
`try_files` shortcut. Where `public/` *can* be written, the mirror or `vendor:publish` is still what
should be serving it.
:::

### Opting out

```php
$packager->hasAssets(mirror: false);
```

Keeps both publish tags, skips the mirror registration entirely. Use it when your deploy publishes
explicitly and you would rather the first request did no filesystem work at all.

### `flush()` — for long-lived workers

Added in **2.4.0**. The singleton is scoped to a request. Under a long-lived worker it outlives one,
and two things go wrong:

- the per-request sync marks limit the mirror to a **single attempt per worker lifetime**, so a
  published copy deleted underneath a running worker is never put back;
- every resolved URL keeps emitting the `?id=<mtime>` of the release the worker booted on — the
  exact query string Livewire watches to notice a deploy.

```php title="a consumer's AppServiceProvider"
use NyonCode\LaravelPackageToolkit\Support\PublishedAssets;

public function boot(): void
{
    $this->app->terminating(function () {
        if (app()->bound(PublishedAssets::class)) {
            app(PublishedAssets::class)->flush();
        }
    });
}
```

`flush()` clears the resolved URLs and the sync marks. It deliberately **keeps** the declared asset
directories: providers register those from `register()`, once per worker boot and not per request,
and clearing them would drop the resolver back to inferring a directory from the asset path — which
only works for a package whose asset directory happens to be named `dist`.

:::note Octane is not a supported target
The toolkit has never been developed against a long-lived worker. `flush()` exists because a
consumer committed to one, not because the toolkit targets them.
:::

## Rendering them in a template

Added in **2.4.0**; discovery in **2.4.1**. Name the files a template renders — or
[name nothing and let them be discovered](#naming-nothing-discovers-them) — and the toolkit
registers the directives that render them:

```php
$packager->hasAssets(entries: ['css/blog.css', 'js/blog.js']);
```

```blade
@packageAssets('blog')                      {{-- everything, stylesheets first --}}
@packageStyles('blog')                      {{-- only the <link> tags --}}
@packageScripts('blog')                     {{-- only the <script> tags --}}
@packageScripts('blog', 'js/blog.js')       {{-- only the entries you name --}}
@packageAssetUrl('blog', 'js/blog.js')         {{-- the bare URL, for your own markup --}}
```

```html title="rendered"
<link rel="stylesheet" href="/vendor/blog/css/blog.css?id=1754640000" data-navigate-track="reload">
<script src="/vendor/blog/js/blog.js?id=1754640000" type="module" data-navigate-track="reload"></script>
```

Every path is relative to the asset directory and is checked at registration — a typo throws
where it was declared, not as a 404 in the browser six screens later. A path that does not exist
throws `FileNotFoundException`, naming both the entry and the directory it was looked for in.

### Naming no package renders every one

Added in **2.4.2**. Drop the short name and the directive renders every package that declared
entries, in the order their providers handed them over:

```blade
@packageAssets      {{-- every package, stylesheets first --}}
@packageStyles
@packageScripts
```

This is the form an application's layout wants. A layout that names its packages is a layout
that has to be edited every time one is installed or removed, in every file that has the line —
and `package:discover` does not help, because it discovers *providers* while the template still
names packages by hand. One line says everything, and keeps saying it.

Stylesheets lead across the whole set, not within each package: the aggregate renders one
document's `<head>`, so a package whose provider booted third is no reason for its stylesheet to
land behind the second package's scripts. Everything else is per entry as before — each package's
`classic()`, its attributes, and its own Vite resolution.

They lead within each of the two halves, not across the seam between them. What the application
built is emitted as one Vite block — preloads, stylesheets, scripts, in Vite's own order — and that
block comes first, so a script the application built precedes a stylesheet that fell back to the
shipped copy. Interleaving the two would mean one Vite call per entry and giving up the single set
of preloads, to reorder a deferred module against a `<link>` the browser fetches without waiting for
it anyway.

`@packageAssetUrl` keeps both arguments. It answers with one URL, and there is no such thing as
the URL of every package.

:::tip Name them when the placement differs
An application that wants only some of its packages in a particular place still names them —
`@packageStyles('blog')` in `<head>` and `@packageScripts('blog')` before `</body>`. The aggregate
is the default, not the only form.
:::

### Naming nothing discovers them

Name no entries and the asset directory answers for itself, the way `hasRoutes()` and `hasViews()`
already discover theirs:

```php
$packager->hasAssets();
```

```text
dist/
├── css/blog.css      → <link rel="stylesheet">
└── js/blog.js        → <script type="module">
```

Discovery looks in the directory `hasAssets()` was given — `hasAssets('public')` discovers
`public/`, not `dist/` — at its root and in its `css/` and `js/` subdirectories, and registers the
stylesheets and scripts it finds there, alphabetically. Extensions are an allowlist
(`css`, `scss`, `sass`, `less`, `styl`, `pcss`, `js`, `mjs`, `cjs`), so the source maps, fonts,
images and `manifest.json` sharing that directory are passed over rather than turned into `<script>`
tags.

**It is not a recursive walk, and that is the point.** A code-split build writes its chunks to a
subdirectory of its own — `assets/` by default — and a chunk is imported *by* an entry point, not
loaded beside it. Giving one its own `<script>` runs the module a second time, in the wrong order,
for no benefit. Stopping at three directories leaves such a build alone:

```text
dist/
├── assets/blog-DkS9x2.js     ✗ a chunk, and not discovered
├── assets/vendor-a91f3c.js   ✗
├── css/blog.css              ✓
└── js/blog.js                ✓
```

Naming any entry replaces discovery outright — the two do not merge — which is also how the two
things a directory listing cannot answer get said:

```php
// A code-split build: name the entry points, leave the chunks to the bundler.
$packager->hasAssets(entries: ['css/blog.css', 'js/blog.js']);

// A discovered script is a module, because that is what a build produces. An IIFE says so.
$packager->hasAssets(entries: [
    'css/blog.css',
    Asset::make('js/blog-legacy.js')->classic(),
]);
```

:::note What it costs
Discovery runs where `hasAssets()` is called — while the packager is being configured, so once per
boot, alongside the eager validation an explicit list already gets. That is up to three directory
listings and no file reads, but unlike [the mirror](#how-the-sync-behaves) it is not deferred until
something renders: a queue worker that will never emit a `<script>` still pays for it. Naming the
entries explicitly is the way to skip it, and for a package whose `dist/` is large that is the
better declaration anyway.
:::

Order matters, and the error says so. `hasAssets()` is what establishes the asset directory, so a
shipped file declared before it has nowhere to be resolved against and throws
`PackageConfigurationException`:

```php
$packager
    ->hasViteAssets(['resources/js/blog.js' => 'js/blog.js'])   // [tl! --]
    ->hasAssets();                                              // [tl! --]
    ->hasAssets()                                               // [tl! ++]
    ->hasViteAssets(['resources/js/blog.js' => 'js/blog.js']);  // [tl! ++]

// Asset [js/blog.js] needs an asset directory. Call hasAssets() before declaring it.
```

The directives take the short name rather than being generated per package (`@blogStyles`). A
generated name exists only when that package is installed, cannot be grepped for, and collides
silently with another package's; the short name is already how the toolkit namespaces views,
translations and publish tags.

### What the hand-written tag misses

On 2.3.0 the mirror existed and the directives did not, so a package's layout had one way to reach a
URL and built the tag around it:

```blade
<script src="{{ app(PublishedAssets::class)->url('blog', $js) }}"></script>
```

That release has been withdrawn, which retires the pattern with it: from 2.4 there is no version
where this is the only option, so it is not a trade-off to weigh — it is code to replace. It is
written up here because it renders fine, which is what keeps it in codebases, and because it is what
a search engine or a model trained on the 2.3 documentation will still hand you.

What it leaves out is the part nobody notices until it matters, and almost all of it fails silently:

- **`$js` has to come from somewhere.** `PublishedAssets::url()` takes an absolute filesystem path,
  so the package needs a class or a view composer holding `__DIR__.'/../dist/js/blog.js'` and
  handing it to the view — the boilerplate `hasAssets(entries: [...])` exists to remove.
- **`url()` returns `?string`.** Where `public/` cannot be written and nothing was published
  before, this renders `src=""`. A browser resolves that against the current page and fetches the
  HTML as a script. Nothing throws, nothing 404s, and the page is broken. The directives emit no
  tag at all in that situation.
- **No `type="module"`.** A Vite bundle has top-level `import`, which is a syntax error in a
  classic script.
- **No `data-navigate-track="reload"`.** The `?id=<mtime>` is then a query string nobody reads:
  Livewire has no reason to full-page-reload a `wire:navigate` visit, so an upgrade lands as new
  markup running against the JavaScript the browser already cached — the exact failure the mirror's
  cache busting was for.
- **No CSP nonce.** Under `Vite::useCspNonce()` a strict policy blocks the tag.
- **No Vite resolution.** An application that compiles this entry into its own build still gets the
  shipped `dist/` copy here, while every directive on the same page serves the built one.
- **The path is unchecked.** A typo resolves to `null`, which is the second point again.
- **`defer`, and stylesheets before scripts**, are then also yours to remember.

The declaration knows every one of these, which is why it is the declaration that renders:

```blade
@packageScripts('blog')
```

For markup the toolkit does not render — an `<img>`, a `<link rel="preload">`, an inline
`import()` — `@packageAssetUrl('blog', 'js/blog.js')` gives the bare URL with the same resolution
behind it.

### Scripts are modules

`.js` renders as `type="module"`, which is what a Vite build produces. A package shipping an
IIFE or UMD bundle must say so — a module is deferred and its top-level declarations never reach
`window`, so a bundle that expects to export a global would silently stop working:

```php
use NyonCode\LaravelPackageToolkit\Support\Asset;

$packager->hasAssets(entries: [
    'css/blog.css',
    Asset::make('js/blog-legacy.js')->classic(),                                // [tl! focus]
    Asset::make('js/blog.js')->attributes(['data-turbo-track' => 'reload']),    // [tl! focus]
]);
```

`Asset` is only needed for what a plain string cannot express. `data-navigate-track="reload"` is
on every tag by default — it is what makes the `?id=` query string mean something to Livewire's
`wire:navigate` — and `->attributes(['data-navigate-track' => null])` removes it.

### When the extension is not the answer

Whether an entry renders as a `<link>` or a `<script>` is inferred from its extension — `css`,
`scss`, `sass`, `less`, `styl` and `pcss` are stylesheets, everything else is a script. That
covers every build whose output extension matches its input. For the build that does not, say it:

```php
$packager->hasAssets(entries: [
    Asset::make('css/blog.blade.php')->asStylesheet(),   // [tl! ~~]
    Asset::make('js/blog.txt')->asScript(),              // [tl! ~~]
]);
```

## Vite — in the application

Added in **2.4.0**. The toolkit ships no Vite config, no build step and no manifest of its own,
and that is the design rather than a gap. What it supports is the other direction: letting the
**consuming application's** Vite build compile the package.

That is the case that actually needs help. An application on Tailwind has to run its own config
over the package's Blade markup or half the package's classes are purged; an application shipping
its own JavaScript would rather not load a second copy of a dependency it already bundles. Both
need the package's *sources* inside the application's build — and then the package's own layout
has to emit a hashed filename it can only learn from the application's manifest.

Declare the sources, and the entries they replace when the application does build them:

```php title="src/BlogServiceProvider.php"
$packager
    ->name('Blog')
    ->hasAssets(entries: ['css/blog.css', 'js/blog.js'])
    ->hasViteAssets([                                                                 // [tl! ++:start]
        // Vite source, relative to the package root => the shipped file it stands in for
        'resources/css/blog.css' => 'css/blog.css',
        'resources/js/blog.js' => 'js/blog.js',
    ]);                                                                               // [tl! ++:end]
```

Nothing else in the package changes. The template still says `@packageAssets('blog')`.

### Four ways to say it

The map above is the shorthand. All four forms below declare the same thing, and mixed forms in one
call are fine:

```php
$packager->hasViteAssets([
    'resources/css/blog.css' => 'css/blog.css',                          // source => shipped fallback
    'resources/js/blog-legacy.js',                                       // source only, no fallback
    'resources/js/blog-umd.js' => Asset::make('js/blog-umd.js')          // [tl! focus:start]
        ->classic(),                                                     // the shorthand, plus presentation
    Asset::vite('resources/js/blog.js')->fallback('js/blog.js')
        ->attributes(['data-turbo-track' => 'reload']),                  // [tl! focus:end]
]);
```

An `Asset` on the right of the arrow is the shorthand with the one thing a bare path cannot carry
attached — `classic()`, `attributes()`, an explicit kind. The key is still the Vite source, so the
asset must not name one of its own: that is two answers to one question, and it throws rather than
picking one.

:::note Fixed in 2.4.3
Before 2.4.3 an `Asset` value took the whole pair and the key was dropped, leaving an entry with no
Vite source at all. It then resolved to the shipped file whatever the application built — which
looks exactly like an application that chose not to build the package.
:::

Declaring the same shipped file in both calls is not a mistake and not a duplicate. A later entry
for a file already declared **replaces** the earlier one, and renders once, in the position it was
first declared — which is exactly what makes the `hasAssets(entries: …)` list above and the
`hasViteAssets()` map naming the same files add up to two tags rather than four.

Replacing is about the tag count, not about starting over. The replacement inherits what the entry
it replaces said about the tag itself, so the shorthand does not have to repeat a `classic()` it has
no way to express:

```php
$packager
    ->hasAssets(entries: [Asset::make('js/blog.js')->classic()])
    ->hasViteAssets(['resources/js/blog.js' => 'js/blog.js']);

// Built by the application → its hashed module.
// Not built → the shipped IIFE, still classic, still deferred.
```

`classic()` is sticky: nothing declares "explicitly a module", so a module cannot be told apart from
the default, and the direction that keeps a working bundle working is the one that survives.
Attributes merge, with the newer declaration winning a collision, and an explicit `asStylesheet()`
or `asScript()` on the replacement stands — the replacement can only have said either deliberately.

:::note Fixed in 2.4.1
Before 2.4.1 the replacement was a blank entry: the pairing above lost its `classic()` and served the
shipped IIFE as `type="module"`, whose top-level declarations never reach `window`. It only showed on
the fallback path — an application that built the entry never saw it — which is what made it worth
naming here.
:::

An application that wants in adds the package's sources to its own config — the path it writes
there is exactly the declared source prefixed with where the package lives:

```js title="the application's vite.config.js"
export default defineConfig({
  plugins: [
    laravel({
      input: [
        'resources/js/app.js',
        'vendor/acme/blog/resources/js/blog.js',    // [tl! ++]
        'vendor/acme/blog/resources/css/blog.css',  // [tl! ++]
      ],
    }),
  ],
})
```

```css title="the application's app.css, for Tailwind"
@source "../../vendor/acme/blog/resources/views";
```

From then on `@packageAssets('blog')` resolves through the application's manifest — hashed
filename, its preloads, its Tailwind pass — and is served hot alongside everything else while
`npm run dev` runs. An application that never touches its `vite.config.js` keeps getting the
shipped files from the mirror, exactly as before.

### Resolution is per entry

Each entry is looked up on its own, so the two modes mix — which is the common case, not an edge
one: an application typically wants the CSS in its build (Tailwind) and is happy with the shipped
JavaScript.

1. `npm run dev` is running → the dev server serves it.
2. The application's manifest has the key → the application's built file.
3. Otherwise → the shipped file, through the [mirror](#the-asset-mirror).

A miss falls back rather than throwing. `@vite` throws on an unknown entry, which is right in an
application's own layout and wrong inside a package's: the package author cannot fix the
application's Vite config, and a 500 on every page is a poor way to say "this could have been
faster".

:::note The dev-server client appears once per directive
While `npm run dev` runs, Laravel prepends `@vite/client` to every block of Vite markup it renders,
and a layout with `@packageStyles` in `<head>` and `@packageScripts` at the end of `<body>` renders
two — so the tag appears twice in the response.

It is a duplicate tag, not a second client. Both carry `type="module"` and the same URL, and a
module URL is fetched and evaluated once per document however many script tags name it, so exactly
one HMR client connects. Suppressing the second would mean remembering across a request that the
first was emitted, and getting that reset wrong under a long-lived worker costs the client
altogether — no HMR, on the one setup where HMR is the point. `@packageAssets` renders a single
block and does not raise the question at all.
:::

### When the prefix cannot be derived

`vendor/acme/blog` is read off the package's own location, which is right for anything installed
by Composer. A package symlinked in from a path repository sits outside the application root, and
there is nothing to derive — say it explicitly:

```php
$packager->hasViteAssets([...], base: 'vendor/acme/blog');
```

Getting this wrong is quiet by design: the manifest lookup misses and the shipped file is served,
which looks exactly like an application that chose not to build the package.

### Knowing which one you got

Falling back is silent, and the most common mistake on the application's side is silent for the
same reason: an input listed under a path one segment off from the manifest key leaves every page
working, served from the shipped file, with nothing saying the build you configured is not being
used.

A package with `hasAbout()` reports it where you already look. The row appears in console only,
and only for a package that declared Vite sources — there is nothing to disambiguate otherwise:

```text
  Blog ..........................................................................
  Version ................................................................ 2.1.0
  Assets .......... css/blog.css: application build, js/blog.js: shipped
```

Or ask directly — `dev server`, `application build`, `shipped`,
[`fallback`](#keeping-the-tag-hasassetfallback), `not published`, `unresolved`:

```php
use NyonCode\LaravelPackageToolkit\Support\PackageAssets;

app(PackageAssets::class)->resolution('blog');
// ['css/blog.css' => 'application build', 'js/blog.js' => 'shipped']
```

Nothing is written to find out. The mirror publishes on demand, so an entry it has not reached yet
reports `shipped` on the strength of the copy being one it could still make — which is asked, not
assumed. Where `public/` cannot be written that copy never appears, the entry is served by the
[fallback](#keeping-the-tag-hasassetfallback) or by nothing at all, and a flat `shipped` would be
this report's own version of the silence it exists to break — on the deployments least equipped to
notice.

### Content Security Policy

`Vite::useCspNonce()` puts a nonce on every tag Laravel generates, and the toolkit carries the same
one onto the tags it renders itself — otherwise a strict policy would load the entry the
application built and block the one falling back to the shipped file. A nonce declared on an entry
wins over the application's.

### Entries with no shipped copy

A package that ships no built assets at all can declare sources alone. Then there is no fallback:
the entry renders when the application built it and renders nothing when it did not.

```php
$packager->hasViteAssets(['resources/js/blog.js']);
```

## A custom directory

```php
$packager->hasAssets('assets');            // ../assets
$packager->hasAssets('public');            // ../public
$packager->hasAssets('resources/dist');    // ../resources/dist
```

The directory is resolved relative to your provider's directory, and must exist — a missing one
throws `DirectoryNotFoundException` at registration.

## Building what you ship

The toolkit builds nothing. The `dist/` you ship is yours to produce, and the setup below is what
pairs with `hasViteAssets()`: a library build whose entries are the same source files an
application would list in its own config, so the two modes stay in step by construction.

```js title="the package's own vite.config.js — for the dist/ it ships"
import { defineConfig } from 'vite'

export default defineConfig({
  build: {
    outDir: 'dist',
    emptyOutDir: true,
    lib: {
      // The same file the application lists as its input, when it builds the package itself.
      entry: { blog: 'resources/js/blog.js' },
      formats: ['es'],
    },
    rollupOptions: {
      output: {
        entryFileNames: 'js/[name].js',
        assetFileNames: 'css/[name][extname]',
      },
    },
  },
})
```

```json title="package.json"
{
    "scripts": {
        "build": "vite build"
    }
}
```

Commit `dist/`. A package consumer runs `composer require`, not `npm run build`.

:::tip Hashed filenames are for the application's build, not yours
The mirror cache-busts with `?id=<mtime>`, and a hashed name in `dist/` would defeat the mtime
comparison its incremental sync depends on while leaving every old hash behind in `public/`. Keep
the shipped names stable — hashing is the application's build's job, and it does it for you the
moment it takes over an entry.
:::

## Introspection

```php
$packager->isAssetable();       // bool
$packager->assetDirectory();    // absolute path
$packager->mirrorsAssets();     // bool — false after hasAssets(mirror: false)
$packager->hasAssetEntries();   // bool — anything declared for a template to render
$packager->assetEntries();      // Support\Asset[] in declaration order
$packager->viteBase();          // ?string — only when given to hasViteAssets()
$packager->assetFallback();     // ?Closure — as given to hasAssetFallback()
```

## Testing

```php
use NyonCode\LaravelPackageToolkit\Support\PublishedAssets;

test('resolving one asset mirrors the whole directory', function () {
    $shipped = __DIR__.'/../dist/css/blog.css';

    $url = app(PublishedAssets::class)->url('blog', $shipped);

    expect(public_path('vendor/blog/css/blog.css'))->toBeFile()
        ->and(public_path('vendor/blog/js/blog.js'))->toBeFile()
        ->and($url)->toBe(
            asset('vendor/blog/css/blog.css')
            .'?id='.filemtime(public_path('vendor/blog/css/blog.css'))
        );
});

test('a published copy newer than the shipped one is left alone', function () {
    $shipped = __DIR__.'/../dist/css/blog.css';
    $published = public_path('vendor/blog/css/blog.css');

    File::ensureDirectoryExists(dirname($published));
    File::put($published, '/* published by hand */');
    touch($published, filemtime($shipped) + 10);

    app(PublishedAssets::class)->url('blog', $shipped);

    expect(File::get($published))->toBe('/* published by hand */');
});
```

`public/` is shared between tests in a way the container is not, so clean up between cases:

```php
beforeEach(fn () => File::deleteDirectory(public_path('vendor/blog')));
```

### Testing the application-built path

Write the manifest the application's build would have written, and render. Laravel memoises parsed
manifests in a static that outlives an application a test rebuilds, so forget it between cases or
the previous test's manifest answers for the next one:

```php
use Illuminate\Foundation\Vite;

function writeApplicationManifest(array $manifest): void
{
    File::ensureDirectoryExists(public_path('build'));
    File::put(public_path('build/manifest.json'), json_encode($manifest));

    (new ReflectionClass(Vite::class))->getProperty('manifests')->setValue(null, []);
}

test('an entry the application built is served from its manifest', function () {
    writeApplicationManifest([
        'vendor/acme/blog/resources/js/blog.js' => [
            'file' => 'assets/blog-a1b2c3.js',
            'src' => 'vendor/acme/blog/resources/js/blog.js',
            'isEntry' => true,
        ],
    ]);

    expect(Blade::render('@packageScripts("blog")'))
        ->toContain('/build/assets/blog-a1b2c3.js')
        ->not->toContain('vendor/blog/js/blog.js');
});
```

Writing `public/hot` covers the dev-server case; deleting both files covers the fallback.
