<?php

declare(strict_types=1);

namespace NyonCode\LaravelPackageToolkit\Support;

use FilesystemIterator;
use NyonCode\LaravelPackageToolkit\Support\Concerns\MirrorsPackageAssets;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Keeps every package's asset directory mirrored into `public/vendor/{short-name}`
 * and resolves a shipped file to that copy.
 *
 * Assets are served as real files under `public/`, not through a route, and the
 * mirror maintains itself — nobody runs a command, nobody edits a vhost. That is not
 * a convenience: route delivery only works when the request reaches PHP, and a very
 * common nginx layout answers `.js` from a `try_files $uri =404` block that never
 * forwards it (the same block 404s Livewire's own `/livewire/livewire.js`). On shared
 * hosting that block is frequently not the application's to change, so a delivery
 * mode that depends on it is not a delivery mode — it is a support ticket. A file
 * that exists is served by every web server configuration there is.
 *
 * `vendor:publish --tag=laravel-assets` (or `--tag={short-name}::assets`) writes the
 * same files to the same place; it is the same operation done ahead of time, and
 * remains the right call for a deploy that would rather not have the first request do
 * it. Neither one is *required*.
 *
 * **The sync is lazy, incremental and self-correcting.** The first asset of a package
 * to resolve a URL in a request compares each shipped file against its published
 * counterpart and copies only what is missing or older — in steady state that is a
 * handful of `stat` calls and no writes at all, and after an upgrade it is one copy
 * per changed file, on one request. Copies land through a temporary file and
 * `rename()`, so a concurrent request never observes a half-written file.
 *
 * Where `public/` cannot be written — a read-only container, Vapor, a hardened
 * deployment — nothing throws: {@see self::url()} returns `null` and the caller falls
 * back to however it served the asset before, and if an older published copy is
 * present it is still preferred. {@see self::isStale()} names such a copy so the
 * caller can warn, because that combination is a production condition.
 *
 * The published copy is cache-busted by its own mtime, which the sync sets to the
 * moment of the copy. That is what keeps `data-navigate-track` meaningful: Livewire
 * full-page-reloads a `wire:navigate` visit when a tracked asset's query string
 * changed, so an upgrade is picked up instead of running new markup against a file
 * the browser already cached.
 *
 * Registered by {@see MirrorsPackageAssets}
 * as a container singleton, so a package is examined at most once per request however
 * many surfaces ask for its URLs. Where the container itself outlives the request —
 * a long-lived worker — {@see self::flush()} puts that memo back to per-request.
 */
class PublishedAssets
{
    /** @var array<string, string|null> shipped path => published URL, or null when nothing is published */
    private array $urls = [];

    /** @var array<string, string> short package name => absolute asset directory, declared by its provider */
    private array $roots = [];

    /** @var array<string, true> packages already mirrored (or attempted) this request */
    private array $synced = [];

    /**
     * Declare where a package ships its assets.
     *
     * Called from the provider, which is the only place that knows for certain — it
     * is where `hasAssets()` named the directory. Reading it back beats inferring one
     * from an asset path, and it is what keeps the mirror pointed at the same
     * directory the publish tags copy. Registration is bookkeeping only: no directory
     * is walked until an asset actually resolves a URL.
     *
     * @param  string  $package  short package name, e.g. `test-package`
     * @param  string  $assetDirectory  absolute path of the package's asset directory
     */
    public function mirrors(string $package, string $assetDirectory): void
    {
        $this->roots[$package] = rtrim($this->normalize($assetDirectory), '/');
    }

    /**
     * The URL of the published copy, mirroring the package first if anything is
     * missing or out of date. `null` only when `public/` could not be written and
     * nothing was published before.
     *
     * @param  string  $package  short package name, e.g. `test-package`
     * @param  string  $path  absolute filesystem path of the shipped asset
     */
    public function url(string $package, string $path): ?string
    {
        $path = $this->normalize($path);

        if (array_key_exists($path, $this->urls)) {
            return $this->urls[$path];
        }

        $this->sync($package, $path);

        $relative = $this->relativeUrl($package, $path);
        $published = @filemtime(public_path($relative));

        if ($published === false) {
            return $this->urls[$path] = null;
        }

        return $this->urls[$path] = asset($relative).'?id='.$published;
    }

    /**
     * Whether the published copy predates the shipped one — reachable only when the
     * sync could not write, since otherwise it would have just replaced it.
     *
     * That copy is still what gets served; this only drives the warning.
     */
    public function isStale(string $package, string $path): bool
    {
        $path = $this->normalize($path);
        $published = @filemtime(public_path($this->relativeUrl($package, $path)));

        return $published !== false && $published < (@filemtime($path) ?: 0);
    }

    /**
     * Forget the resolved URLs and the per-request sync marks.
     *
     * For a long-lived worker, where this singleton outlives the request it was scoped
     * to: `$synced` would limit the mirror to one attempt per worker lifetime, so a
     * published copy deleted under a running worker would never be put back, and
     * `$urls` would keep emitting the `?id=<mtime>` of the release the worker booted
     * on — the very query string Livewire's `data-navigate-track` watches to notice a
     * deploy. Call it from the framework's request-terminated hook.
     *
     * The declared directories are deliberately kept: providers register those from
     * `register()`, once per worker boot and not per request, so clearing them would
     * drop {@see self::assetRoot()} to its `/dist/` inference — which only holds for a
     * package whose asset directory happens to be named `dist`.
     */
    public function flush(): void
    {
        $this->urls = [];
        $this->synced = [];
    }

    /**
     * Mirror one package's asset directory into `public/vendor/{short-name}`, once
     * per request.
     *
     * Every shipped file is compared, not just the ones a caller asked about: a
     * code-split entry point imports `./chunk-<hash>.js`, which the browser fetches
     * directly and PHP is never asked to resolve, so a mirror driven only by resolved
     * URLs would leave that chunk behind and break the bundle.
     */
    private function sync(string $package, string $path): void
    {
        if (isset($this->synced[$package])) {
            return;
        }

        $this->synced[$package] = true;

        $root = $this->assetRoot($package, $path);

        if ($root === null) {
            return;
        }

        $target = public_path('vendor/'.$package);

        foreach ($this->shippedFiles($root) as $relative => $source) {
            $destination = $target.'/'.$relative;
            $published = @filemtime($destination);

            if ($published !== false && $published >= (@filemtime($source) ?: 0)) {
                continue;
            }

            $this->copyAtomically($source, $destination);
        }
    }

    /**
     * Copy through a temporary file in the destination directory, then `rename()`.
     *
     * `rename()` within one filesystem is atomic, so a request that fetches the file
     * while another is mid-copy reads either the whole old one or the whole new one —
     * never a truncated file, which for a bundle would fail as a syntax error and take
     * everything in it down with it.
     */
    private function copyAtomically(string $source, string $destination): void
    {
        $directory = dirname($destination);

        if (! is_dir($directory) && ! @mkdir($directory, 0o755, true) && ! is_dir($directory)) {
            return;
        }

        $temporary = $destination.'.'.bin2hex(random_bytes(8)).'.tmp';

        if (! @copy($source, $temporary)) {
            @unlink($temporary);

            return;
        }

        if (! @rename($temporary, $destination)) {
            @unlink($temporary);
        }
    }

    /**
     * Every file under a package's asset directory, keyed by its path relative to it
     * — which is also its path under `public/vendor/{short-name}`, since the mirror is
     * verbatim.
     *
     * @return array<string, string> relative path => absolute source path
     */
    private function shippedFiles(string $root): array
    {
        if (! is_dir($root)) {
            return [];
        }

        $files = [];
        $prefix = strlen(rtrim($root, '/\\')) + 1;

        /** @var iterable<\SplFileInfo> $iterator */
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files[str_replace('\\', '/', substr($file->getPathname(), $prefix))] = $file->getPathname();
            }
        }

        return $files;
    }

    /**
     * One canonical, forward-slashed spelling of a path, so a root and an asset under
     * it actually prefix-match.
     *
     * They otherwise need not: `hasAssets()` builds its directory by appending
     * `../{directory}` to the package's `src/`, which leaves a literal `/../` in the
     * string, while a caller naming an asset builds an already-resolved path. Without
     * this, {@see self::relativeUrl()} would quietly fall back to the basename and
     * flatten a directory the mirror copies verbatim.
     *
     * `realpath()` is cheap here — PHP caches it, and {@see self::url()} asks once per
     * asset — and returns `false` for a path that does not exist, which is not an
     * error: the string is then used as given and the caller gets the same `null` it
     * would have anyway.
     */
    private function normalize(string $path): string
    {
        return str_replace('\\', '/', realpath($path) ?: $path);
    }

    /**
     * The package's asset directory: what its provider declared, or — for an asset
     * registered by an application rather than a package — the directory read back
     * off the asset path.
     *
     * `null` when neither is available. Such an asset is still resolved and served by
     * the caller; it just has no directory for the mirror to walk.
     */
    private function assetRoot(string $package, string $path): ?string
    {
        if (isset($this->roots[$package])) {
            return $this->roots[$package];
        }

        $normalized = str_replace('\\', '/', $path);
        $marker = strrpos($normalized, '/dist/');

        return $marker === false ? null : substr($normalized, 0, $marker + 5);
    }

    /**
     * The publish-relative URL path of one asset: `vendor/{short-name}/{path inside
     * the asset directory}`.
     *
     * The path *inside* the directory is kept rather than just the basename, because
     * the mirror copies it verbatim and entry points commonly live one level down —
     * flattening them would break the relative chunk imports that make a code-split
     * bundle work at all.
     */
    private function relativeUrl(string $package, string $path): string
    {
        $normalized = str_replace('\\', '/', $path);
        $root = $this->assetRoot($package, $path);

        $inside = $root !== null && str_starts_with($normalized, $root.'/')
            ? substr($normalized, strlen($root) + 1)
            : basename($normalized);

        return 'vendor/'.$package.'/'.$inside;
    }
}
