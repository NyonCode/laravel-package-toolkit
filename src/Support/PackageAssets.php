<?php

declare(strict_types=1);

namespace NyonCode\LaravelPackageToolkit\Support;

use Closure;
use Illuminate\Foundation\Vite;
use Illuminate\Foundation\ViteException;
use Illuminate\Support\HtmlString;
use NyonCode\LaravelPackageToolkit\Support\Concerns\DeclaresPackageAssets;

/**
 * Turns a package's declared assets into the tags a template needs, resolving each one
 * through the *application's* Vite build when that build covers it and through the
 * package's own shipped files when it does not.
 *
 * Both halves matter, and which one applies is the application's choice, not the
 * package's. An application that adds `vendor/acme/blog/resources/js/blog.js` to its
 * `vite.config.js` gets the package compiled into its own build — one manifest, its own
 * Tailwind config applied to the package's Blade views, the package's dependencies
 * deduplicated against the application's — and while `npm run dev` runs, the package is
 * served hot with everything else. An application that does not touch its Vite config
 * gets the built files the package shipped, mirrored into `public/vendor/{short-name}`
 * by {@see PublishedAssets}. The package declares once and renders the same
 * `@packageAssets('blog')` either way.
 *
 * The toolkit deliberately ships no Vite config, no build step and no manifest of its
 * own. A package that built assets with its own Vite would still have to hand them to a
 * template as plain files — which the mirror already does — while a *second* manifest,
 * a second dev server and a second set of hashed filenames buy nothing the application's
 * build does not already do better. So Vite support here is exactly one thing: making
 * the application's build a first-class way to serve a package's assets.
 *
 * Resolution is per entry, so the two modes mix: an application can build the package's
 * CSS (the common case, since Tailwind must see the package's markup) and leave its
 * JavaScript to the shipped bundle. Anything the application's manifest does not have
 * quietly falls back rather than throwing — a missing entry is a deployment state, not a
 * programming error, and `@vite` throwing inside a package's own layout would take down
 * a page the package author cannot fix from the application's side.
 *
 * Registered as a container singleton by {@see DeclaresPackageAssets}, which is also
 * where each package's declaration is handed over.
 */
class PackageAssets
{
    /**
     * @var array<string, array{directory: string, entries: list<Asset>, base: string|null, mirrored: bool, fallback: Closure|null}>
     */
    private array $packages = [];

    /**
     * Declare what one package renders.
     *
     * @param  string  $package  short package name, e.g. `blog`
     * @param  string  $directory  absolute path of the package's asset directory, `''` when it ships none
     * @param  list<Asset>  $entries
     * @param  string|null  $base  the package's path under the application, prefixed onto every Vite source
     * @param  bool  $mirrored  whether {@see PublishedAssets} keeps `public/` in step with the shipped files
     * @param  Closure|null  $fallback  where to serve a shipped file from when nothing is published, `fn (string $file, string $package): ?string`
     */
    public function declare(string $package, string $directory, array $entries, ?string $base, bool $mirrored, ?Closure $fallback = null): void
    {
        $this->packages[$package] = [
            'directory' => $directory,
            'entries' => $entries,
            'base' => $base,
            'mirrored' => $mirrored,
            'fallback' => $fallback,
        ];
    }

    /**
     * Every declared tag for a package, or only the named entries.
     *
     * Naming no package renders every package that declared entries, in the order their
     * providers handed them over. That is the form an application's layout wants: one
     * line that still says everything after the application installs another package of
     * the same family, where a required short name means editing every layout that has
     * one. `package:discover` does not help — it discovers *providers*, and the template
     * still names packages by hand.
     *
     * @param  string|null  $package  short package name, e.g. `blog`; `null` for every package that declared entries
     * @param  string  ...$only  entry keys — the shipped file, or the Vite source when there is none
     */
    public function tags(?string $package = null, string ...$only): HtmlString
    {
        return $this->render($this->selection($package, $only));
    }

    /**
     * Only the stylesheet tags — for a layout that puts them in `<head>` and the scripts
     * at the end of `<body>`.
     */
    public function styles(?string $package = null, string ...$only): HtmlString
    {
        return $this->render(array_filter(
            $this->selection($package, $only),
            fn (array $selected): bool => $selected[1]->isStylesheet(),
        ));
    }

    /**
     * Only the script tags.
     */
    public function scripts(?string $package = null, string ...$only): HtmlString
    {
        return $this->render(array_filter(
            $this->selection($package, $only),
            fn (array $selected): bool => ! $selected[1]->isStylesheet(),
        ));
    }

    /**
     * The URL of one entry, for markup the toolkit does not render — an `<img>`, an
     * inline `import()`, a `<link rel="preload">`.
     *
     * `null` when the application's build does not have it and nothing is published.
     *
     * @param  string  $package  short package name, e.g. `blog`
     * @param  string  $entry  entry key — the shipped file, or the Vite source when there is none
     */
    public function url(string $package, string $entry): ?string
    {
        $assets = $this->entries($package, [$entry]);
        $asset = reset($assets);

        if ($asset === false) {
            return null;
        }

        $key = $this->viteKey($package, $asset);

        if ($key !== null) {
            try {
                return $this->vite()?->asset($key);
            } catch (ViteException) {
                // No manifest, or the application never listed this input — fall through
                // to the shipped copy. Anything else (a manifest that is not valid JSON,
                // a chunk with no `file`) is a broken build, and surfaces.
            }
        }

        return $this->publishedUrl($package, $asset);
    }

    /**
     * Whether a package has anything declared. `@packageAssets` for a package that never
     * declared one renders nothing rather than failing.
     */
    public function declared(string $package): bool
    {
        return ($this->packages[$package]['entries'] ?? []) !== [];
    }

    /**
     * How each entry resolves right now, as `entry key => 'dev server' | 'application
     * build' | 'shipped' | 'fallback' | 'not published' | 'unresolved'`.
     *
     * The counterpart to {@see PublishedAssets::isStale()}, and it exists for the same
     * reason. Falling back is silent by design — a package's layout cannot fix the
     * application's Vite config — but the most common mistake on that side is invisible
     * for exactly the same reason: an input listed under a path that differs from the
     * manifest key by one segment leaves everything working, just not through the build
     * the developer set up. Something has to be able to say so, and this is it. The
     * package's `about` section says it for you when it has one.
     *
     * Nothing is written: the mirror publishes on demand, and an entry it has not reached
     * yet reports `shipped` on the strength of the copy being one it could still make,
     * rather than making it to find out.
     *
     * Which is why "could still make" is asked rather than assumed. An unwritable
     * `public/` is the one shape where the mirror never produces that copy, the entry is
     * served by {@see self::fallbackUrl()} or not at all, and a flat `shipped` would be
     * this method's own version of the silence it exists to break — on the deployments
     * least equipped to notice.
     *
     * @return array<string, string>
     */
    public function resolution(string $package): array
    {
        $directory = $this->packages[$package]['directory'] ?? '';
        $mirrored = $this->packages[$package]['mirrored'] ?? false;
        $resolution = [];

        foreach ($this->entries($package, []) as $asset) {
            $key = $this->viteKey($package, $asset);
            $file = $asset->file();

            $resolution[$asset->key()] = match (true) {
                $key !== null && ($this->vite()?->isRunningHot() ?? false) => 'dev server',
                $key !== null && $this->builtByApplication($key) => 'application build',
                $file === null || $directory === '' => 'unresolved',
                is_file(public_path('vendor/'.$package.'/'.$file)) => 'shipped',
                $mirrored && $this->publishable($package) => 'shipped',
                $this->fallbackUrl($package, $file) !== null => 'fallback',
                default => 'not published',
            };
        }

        return $resolution;
    }

    /**
     * Whether the mirror could still write this package's copy under `public/`.
     *
     * The target directory usually does not exist yet — the mirror creates it on the
     * first request that resolves a URL — so what is tested is the nearest ancestor that
     * does, which is the one {@see PublishedAssets} would have to create it in. Walking
     * up rather than testing `public/` alone matters for the deployment this is here to
     * catch: a `public/vendor` shipped read-only inside an image sits under a writable
     * `public/`, and asking only the top would call it publishable.
     */
    private function publishable(string $package): bool
    {
        $directory = public_path('vendor/'.$package);

        while (! is_dir($directory)) {
            $parent = dirname($directory);

            if ($parent === $directory) {
                return false;
            }

            $directory = $parent;
        }

        return is_writable($directory);
    }

    /**
     * The entries a call names, each paired with the package that declared it — one
     * package's, or every package's when none was named.
     *
     * The pairing is what the aggregate form needs and a per-package render did not: a
     * Vite manifest key is only meaningful against the `base` of the package the entry
     * belongs to, so the package cannot be a parameter of the render any more.
     *
     * @param  array<int, string>  $only
     * @return list<array{0: string, 1: Asset}>
     */
    private function selection(?string $package, array $only): array
    {
        $selected = [];

        foreach ($package === null ? array_keys($this->packages) : [$package] as $name) {
            foreach ($this->entries($name, $only) as $asset) {
                $selected[] = [$name, $asset];
            }
        }

        return $selected;
    }

    /**
     * The declared entries, narrowed to the requested keys when any were named.
     *
     * @param  array<int, string>  $only
     * @return list<Asset>
     */
    private function entries(string $package, array $only): array
    {
        $entries = $this->packages[$package]['entries'] ?? [];

        if ($only === []) {
            return $entries;
        }

        $wanted = array_map(
            fn (string $key): string => ltrim(str_replace('\\', '/', $key), '/'),
            $only,
        );

        return array_values(array_filter(
            $entries,
            fn (Asset $asset): bool => in_array($asset->key(), $wanted, true)
                || in_array((string) $asset->source(), $wanted, true),
        ));
    }

    /**
     * Render a set of entries: the application's build first, in one call so Vite emits a
     * single set of preloads, then whatever it did not cover — stylesheets before
     * scripts, since a script tag the browser reaches first should not be the thing that
     * delays the styles.
     *
     * Stylesheets lead across the whole set rather than within each package: an aggregate
     * call renders one document's `<head>`, and a package whose provider booted third is
     * no reason for its stylesheet to land behind the second package's scripts.
     *
     * They lead within each of the two halves, though, not across the seam. Vite orders
     * its own block — preloads, then stylesheets, then scripts — and that block is
     * emitted whole, so a script the application built precedes a stylesheet that fell
     * back to the shipped copy. Interleaving the two would mean calling Vite per entry
     * and giving up the single set of preloads, to reorder a deferred module against a
     * `<link>` the browser fetches without waiting for it either way. The seam is left
     * where it is.
     *
     * @param  iterable<array{0: string, 1: Asset}>  $selected
     */
    private function render(iterable $selected): HtmlString
    {
        $viteKeys = [];
        $styles = [];
        $scripts = [];

        foreach ($selected as [$package, $asset]) {
            $key = $this->viteKey($package, $asset);

            if ($key !== null && $this->builtByApplication($key)) {
                $viteKeys[] = $key;

                continue;
            }

            $tag = $this->publishedTag($package, $asset);

            if ($tag === null) {
                continue;
            }

            if ($asset->isStylesheet()) {
                $styles[] = $tag;
            } else {
                $scripts[] = $tag;
            }
        }

        $html = [];

        if ($viteKeys !== []) {
            $vite = $this->vite();

            if ($vite !== null) {
                try {
                    $html[] = $vite($viteKeys)->toHtml();
                } catch (ViteException) {
                    // The manifest was replaced between the lookup above and this call —
                    // a deploy landing mid-request. Nothing to emit; the shipped tags
                    // below still stand.
                }
            }
        }

        return new HtmlString(implode("\n", array_merge($html, $styles, $scripts)));
    }

    /**
     * The manifest key for an entry — the Vite source prefixed with the package's path
     * under the application — or `null` when the entry declares no source, or when the
     * package's location could not be derived and was not given.
     */
    private function viteKey(string $package, Asset $asset): ?string
    {
        $source = $asset->source();
        $base = $this->packages[$package]['base'] ?? null;

        if ($source === null || $base === null) {
            return null;
        }

        return $base === '' ? $source : $base.'/'.$source;
    }

    /**
     * Whether the application's build covers this key.
     *
     * While `npm run dev` runs there is no manifest to consult and every key is served by
     * the dev server, so hot mode answers yes for anything declared: an application
     * running Vite hot has its own config open in front of it, and a 404 from the dev
     * server names the missing input far more clearly than a silently substituted stale
     * bundle would.
     */
    private function builtByApplication(string $key): bool
    {
        $vite = $this->vite();

        if ($vite === null) {
            return false;
        }

        if ($vite->isRunningHot()) {
            return true;
        }

        try {
            $vite->asset($key);

            return true;
        } catch (ViteException) {
            return false;
        }
    }

    /**
     * The `<link>` or `<script>` for a shipped file, or `null` when there is none to
     * serve.
     */
    private function publishedTag(string $package, Asset $asset): ?string
    {
        $url = $this->publishedUrl($package, $asset);

        if ($url === null) {
            return null;
        }

        $attributes = $this->attributes($this->withCspNonce($asset->tagAttributes()));

        if ($asset->isStylesheet()) {
            return '<link rel="stylesheet" href="'.e($url).'"'.$attributes.'>';
        }

        $type = $asset->isModule() ? ' type="module"' : '';

        return '<script src="'.e($url).'"'.$type.$attributes.'></script>';
    }

    /**
     * The URL of the shipped file — through the mirror, which publishes it first if it is
     * missing or out of date, or straight off `public/` for a package that opted the
     * mirror out with `hasAssets(mirror: false)`, or from wherever the package said to
     * serve it when neither produced anything.
     */
    private function publishedUrl(string $package, Asset $asset): ?string
    {
        $file = $asset->file();
        $directory = $this->packages[$package]['directory'] ?? '';

        if ($file === null || $directory === '') {
            return null;
        }

        return $this->mirroredUrl($package, $directory, $file)
            ?? $this->fallbackUrl($package, $file);
    }

    /**
     * The published copy's URL, or `null` when there is none under `public/` to serve.
     */
    private function mirroredUrl(string $package, string $directory, string $file): ?string
    {
        if ($this->packages[$package]['mirrored'] ?? false) {
            return app(PublishedAssets::class)->url($package, $directory.DIRECTORY_SEPARATOR.$file);
        }

        $relative = 'vendor/'.$package.'/'.$file;
        $published = @filemtime(public_path($relative));

        return $published === false ? null : asset($relative).'?id='.$published;
    }

    /**
     * Where the package said to serve a shipped file from when nothing is published.
     *
     * Without one, {@see self::render()} drops the tag. That is the right call for an
     * entry the application chose not to build, and the wrong one for the entry that is
     * the package's only copy: the page loses its stylesheet or its behaviour with
     * nothing in the markup, the log or the console to say why. An unwritable `public/`
     * is a deployment shape — a hardened container, Vapor, shared hosting — not a
     * mistake anyone is about to go looking for.
     *
     * A package that also serves its assets from a route of its own declares it with
     * `hasAssetFallback()` and keeps the tag. The resolver owns the whole URL, including
     * any cache-busting query string: the mtime this class appends elsewhere is the
     * published copy's, and the whole point here is that there is no published copy.
     *
     * Returning `null` means the package has nothing either, and the tag is dropped as
     * before.
     */
    private function fallbackUrl(string $package, string $file): ?string
    {
        $fallback = $this->packages[$package]['fallback'] ?? null;

        if ($fallback === null) {
            return null;
        }

        $url = $fallback($file, $package);

        return is_string($url) && $url !== '' ? $url : null;
    }

    /**
     * Carry the application's CSP nonce onto a tag the toolkit renders itself.
     *
     * `Vite::useCspNonce()` puts one on every tag Laravel generates, so without this the
     * two halves of the same declaration behave differently under a strict policy: the
     * entry the application built loads and the one falling back to the shipped file is
     * blocked — the inconsistency the fallback exists to avoid. Laravel nonces both
     * stylesheets and scripts, and so does this. An explicitly declared `nonce` wins.
     *
     * @param  array<string, string|bool|null>  $attributes
     * @return array<string, string|bool|null>
     */
    private function withCspNonce(array $attributes): array
    {
        $nonce = $this->vite()?->cspNonce();

        if ($nonce === null || array_key_exists('nonce', $attributes)) {
            return $attributes;
        }

        return $attributes + ['nonce' => $nonce];
    }

    /**
     * Render declared attributes: `true` renders bare, `false` and `null` drop the
     * attribute — which is how a default is removed.
     *
     * @param  array<string, string|bool|null>  $attributes
     */
    private function attributes(array $attributes): string
    {
        $rendered = '';

        foreach ($attributes as $name => $value) {
            if ($value === false || $value === null) {
                continue;
            }

            $rendered .= $value === true
                ? ' '.$name
                : ' '.$name.'="'.e($value).'"';
        }

        return $rendered;
    }

    /**
     * The application's Vite instance, or `null` where there is none — the toolkit
     * requires `illuminate/support`, not the full framework.
     */
    private function vite(): ?Vite
    {
        if (! class_exists(Vite::class)) {
            return null;
        }

        /** @var Vite */
        return app(Vite::class);
    }
}
