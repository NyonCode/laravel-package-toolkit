<?php

declare(strict_types=1);

namespace NyonCode\LaravelPackageToolkit\Support;

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
     * @var array<string, array{directory: string, entries: list<Asset>, base: string|null, mirrored: bool}>
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
     */
    public function declare(string $package, string $directory, array $entries, ?string $base, bool $mirrored): void
    {
        $this->packages[$package] = [
            'directory' => $directory,
            'entries' => $entries,
            'base' => $base,
            'mirrored' => $mirrored,
        ];
    }

    /**
     * Every declared tag for a package, or only the named entries.
     *
     * @param  string  $package  short package name, e.g. `blog`
     * @param  string  ...$only  entry keys — the shipped file, or the Vite source when there is none
     */
    public function tags(string $package, string ...$only): HtmlString
    {
        return $this->render($package, $this->entries($package, $only));
    }

    /**
     * Only the stylesheet tags — for a layout that puts them in `<head>` and the scripts
     * at the end of `<body>`.
     */
    public function styles(string $package, string ...$only): HtmlString
    {
        return $this->render($package, array_filter(
            $this->entries($package, $only),
            fn (Asset $asset): bool => $asset->isStylesheet(),
        ));
    }

    /**
     * Only the script tags.
     */
    public function scripts(string $package, string ...$only): HtmlString
    {
        return $this->render($package, array_filter(
            $this->entries($package, $only),
            fn (Asset $asset): bool => ! $asset->isStylesheet(),
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
     * build' | 'shipped' | 'not published' | 'unresolved'`.
     *
     * The counterpart to {@see PublishedAssets::isStale()}, and it exists for the same
     * reason. Falling back is silent by design — a package's layout cannot fix the
     * application's Vite config — but the most common mistake on that side is invisible
     * for exactly the same reason: an input listed under a path that differs from the
     * manifest key by one segment leaves everything working, just not through the build
     * the developer set up. Something has to be able to say so, and this is it. The
     * package's `about` section says it for you when it has one.
     *
     * Nothing is written: an entry the mirror would publish on demand reports `shipped`
     * on the strength of the file existing, rather than publishing it to find out.
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
                $mirrored => 'shipped',
                is_file(public_path('vendor/'.$package.'/'.$file)) => 'shipped',
                default => 'not published',
            };
        }

        return $resolution;
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
     * @param  iterable<Asset>  $assets
     */
    private function render(string $package, iterable $assets): HtmlString
    {
        $viteKeys = [];
        $styles = [];
        $scripts = [];

        foreach ($assets as $asset) {
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
     * mirror out with `hasAssets(mirror: false)`.
     */
    private function publishedUrl(string $package, Asset $asset): ?string
    {
        $file = $asset->file();
        $directory = $this->packages[$package]['directory'] ?? '';

        if ($file === null || $directory === '') {
            return null;
        }

        if ($this->packages[$package]['mirrored'] ?? false) {
            return app(PublishedAssets::class)->url($package, $directory.DIRECTORY_SEPARATOR.$file);
        }

        $relative = 'vendor/'.$package.'/'.$file;
        $published = @filemtime(public_path($relative));

        return $published === false ? null : asset($relative).'?id='.$published;
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
