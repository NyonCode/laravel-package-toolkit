<?php

namespace NyonCode\LaravelPackageToolkit\Concerns;

use Closure;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use NyonCode\LaravelPackageToolkit\PackageConfigurationException;
use NyonCode\LaravelPackageToolkit\Support\Asset;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;

trait HasAssets
{
    use FilesResolver;

    /**
     * The directories discovery looks in, relative to whichever asset directory
     * `hasAssets()` established — its own root, and the two subdirectories a built
     * package conventionally splits into. `''` is the root itself.
     *
     * Not a recursive walk, and that is the point. A code-split build writes its chunks
     * to a subdirectory of its own (`assets/` by default), and a chunk is imported *by*
     * an entry point rather than loaded beside it — giving one its own `<script>` runs
     * the module twice, in the wrong order, for no benefit. Discovery that stops at these
     * three directories leaves such a build alone; a package that ships one names its
     * entry points explicitly, which it had to do anyway.
     */
    private const DISCOVERED_DIRECTORIES = ['', 'css', 'js'];

    /**
     * The extensions discovery registers, as an allowlist.
     *
     * An allowlist rather than "everything that is not a stylesheet", because an asset
     * directory holds more than tags: source maps, fonts, images and a `manifest.json`
     * all live there, and {@see Asset::isStylesheet()} would class every one of them as a
     * script.
     */
    private const DISCOVERED_EXTENSIONS = [
        'css', 'scss', 'sass', 'less', 'styl', 'pcss',
        'js', 'mjs', 'cjs',
    ];

    /**
     * @var bool Whether the package has assets
     */
    private bool $isAssetable = false;

    /**
     * @var string The directory name where the assets are located
     */
    private string $assetDirectory = '';

    /**
     * @var bool Whether the assets are mirrored into `public/vendor/<short-name>`
     */
    private bool $mirrorsAssets = true;

    /**
     * @var list<Asset> The assets a template asks for by name, in declaration order
     */
    private array $assetEntries = [];

    /**
     * @var string|null The package's location under the application, for Vite manifest keys
     */
    private ?string $viteBase = null;

    /**
     * @var Closure|null Where to serve a shipped file from when nothing is published
     */
    private ?Closure $assetFallback = null;

    /**
     * Whether the package has assets.
     */
    public function isAssetable(): bool
    {
        return $this->isAssetable;
    }

    public function assetDirectory(): string
    {
        return $this->assetDirectory;
    }

    /**
     * Whether the package's assets are kept mirrored under `public/`.
     */
    public function mirrorsAssets(): bool
    {
        return $this->mirrorsAssets;
    }

    /**
     * The declared entries, in the order a template renders them.
     *
     * @return list<Asset>
     */
    public function assetEntries(): array
    {
        return $this->assetEntries;
    }

    /**
     * Whether anything is declared for `@packageAssets` to render.
     */
    public function hasAssetEntries(): bool
    {
        return $this->assetEntries !== [];
    }

    /**
     * The package's path relative to the application root, prefixed onto every Vite
     * source to form the manifest key. `null` when it was neither declared nor derivable.
     */
    public function viteBase(): ?string
    {
        return $this->viteBase;
    }

    /**
     * The declared last resort for a shipped file that nothing published, or `null`.
     */
    public function assetFallback(): ?Closure
    {
        return $this->assetFallback;
    }

    /**
     * Where to serve a shipped file from when nothing is published.
     *
     * `@packageAssets` renders the mirrored copy, and where `public/` cannot be written
     * there is none to render — so the tag is dropped, and a page quietly loses its
     * stylesheet or its behaviour. That is fine for an entry the application declined to
     * build and wrong for the entry that is the package's only copy, and the deployments
     * it happens on (a read-only container, Vapor, shared hosting) are the ones least
     * likely to notice. A package that also serves its assets from a route of its own
     * points at it here and keeps the tag, with everything the declaration said about it
     * — `classic()`, attributes, `data-navigate-track`, the application's CSP nonce —
     * still on it.
     *
     * The resolver is handed the entry's file path relative to the asset directory and
     * the package's short name, and owns the whole URL it returns, cache-busting query
     * string included: the mtime the renderer appends belongs to the published copy, and
     * the point of being here is that there is not one. Returning `null` means the
     * package has nothing either, and the tag is dropped as before.
     *
     * ```php
     * $packager
     *     ->hasAssets(entries: ['js/blog.js'])
     *     ->hasAssetFallback(fn (string $file): string => route('blog.asset', ['file' => $file]));
     * ```
     *
     * @param  Closure  $resolver  `fn (string $file, string $package): ?string`
     *
     * @throws PackageConfigurationException if declared before `hasAssets()`
     */
    public function hasAssetFallback(Closure $resolver): static
    {
        if (! $this->isAssetable) {
            throw new PackageConfigurationException(
                'An asset fallback needs an asset directory. Call hasAssets() before declaring it.'
            );
        }

        $this->assetFallback = $resolver;

        return $this;
    }

    /**
     * Enable the package's assets.
     *
     * Naming no entries discovers them, the way `hasRoutes()` and `hasViews()` discover
     * their directories: the stylesheets and scripts directly inside the asset directory
     * and its `css/` and `js/` subdirectories become entries, in a stable alphabetical
     * order, and the Blade directives render them. Two things stay the packager's job,
     * because no filesystem scan can answer them — an IIFE or UMD bundle still needs
     * `Asset::make(…)->classic()`, since a discovered script is emitted as a module, and a
     * code-split build still needs its entry points named, since discovery deliberately
     * does not descend into a chunk directory. Naming anything at all replaces discovery
     * outright; the two do not merge.
     *
     * @param  string  $directory  The directory name where the assets are located
     * @param  bool  $mirror  Whether to keep the assets mirrored into `public/vendor/<short-name>`
     * @param  array<int|string, Asset|string>  $entries  The files a template renders, relative to `$directory`
     *
     * @throws DirectoryNotFoundException if the directory does not exist
     * @throws FileNotFoundException if a declared entry does not exist
     */
    public function hasAssets(string $directory = 'dist', bool $mirror = true, array $entries = []): static
    {
        $path = $this->path("../$directory");

        if (! File::isDirectory($path)) {
            throw new DirectoryNotFoundException(
                "Directory [$path] does not exist"
            );
        }
        $this->assetDirectory = $path;
        $this->isAssetable = true;
        $this->mirrorsAssets = $mirror;

        foreach ($entries === [] ? $this->discoverAssetEntries() : $entries as $entry) {
            $this->declareAsset(
                $entry instanceof Asset ? $entry : Asset::make($entry)
            );
        }

        return $this;
    }

    /**
     * The entries to register when the packager named none.
     *
     * Sorted, because the order entries are declared in is the order `@packageAssets`
     * renders them and a directory listing is not ordered by anything in particular —
     * `File::files()` hands back whatever the filesystem does, which differs between a
     * developer's machine and the server. Stylesheets are emitted before scripts
     * regardless, by the renderer; this only settles the order within each.
     *
     * @return list<string> paths relative to the asset directory
     */
    private function discoverAssetEntries(): array
    {
        $discovered = [];

        foreach (self::DISCOVERED_DIRECTORIES as $subdirectory) {
            $directory = $subdirectory === ''
                ? $this->assetDirectory
                : $this->assetDirectory.DIRECTORY_SEPARATOR.$subdirectory;

            if (! File::isDirectory($directory)) {
                continue;
            }

            foreach (File::files($directory) as $file) {
                if (! in_array(strtolower($file->getExtension()), self::DISCOVERED_EXTENSIONS, true)) {
                    continue;
                }

                $discovered[] = $subdirectory === ''
                    ? $file->getFilename()
                    : $subdirectory.'/'.$file->getFilename();
            }
        }

        sort($discovered);

        return $discovered;
    }

    /**
     * Declare assets the *consuming application's* Vite build compiles.
     *
     * The toolkit builds nothing and ships no Vite config; this is the other side of
     * that. An application that wants the package's CSS to go through its own Tailwind
     * config, or its JavaScript to share the application's dependency graph, adds the
     * package's source file to its own `vite.config.js`:
     *
     * ```js
     * laravel({ input: ['resources/js/app.js', 'vendor/acme/blog/resources/js/blog.js'] })
     * ```
     *
     * The path it lists there is the manifest key, and this method declares the half of
     * it that belongs to the package. At render time `@packageAssets` looks the key up in
     * the application's manifest — or serves it from the dev server while `npm run dev`
     * is running — and falls back to the shipped file for every entry the application did
     * not build. Nothing about the package changes between the two modes, and an
     * application that never touches its `vite.config.js` keeps working exactly as before.
     *
     * Accepts `Asset::vite(...)->fallback(...)`, or the shorthand `source => fallback`
     * map, or a plain list of sources when the package ships no built copy of them.
     *
     * @param  array<int|string, Asset|string>  $entries
     * @param  string|null  $base  The package's path under the application, when it cannot be derived
     *
     * @throws FileNotFoundException if a declared source or fallback does not exist
     * @throws PackageConfigurationException if a fallback is declared before `hasAssets()`
     */
    public function hasViteAssets(array $entries, ?string $base = null): static
    {
        if ($base !== null) {
            $this->viteBase = trim(str_replace('\\', '/', $base), '/');
        }

        foreach ($entries as $source => $entry) {
            $this->declareAsset(match (true) {
                $entry instanceof Asset => $entry,
                is_string($source) => Asset::vite($source)->fallback($entry),
                default => Asset::vite($entry),
            });
        }

        return $this;
    }

    /**
     * Validate one declared entry and add it, replacing any earlier entry for the same
     * file so a `hasAssets(entries: …)` shipped file and the `hasViteAssets()` entry that
     * falls back to it render once, in the position first declared.
     *
     * The replacement inherits what the entry it replaces said about the *tag* — see
     * {@see Asset::inheritPresentationFrom()}. Replacing is about not rendering one file
     * twice; it was never meant to discard a `classic()` or an attribute that the second
     * declaration, being a shorthand, had no way to repeat.
     *
     * @throws FileNotFoundException
     * @throws PackageConfigurationException
     */
    private function declareAsset(Asset $asset): void
    {
        $file = $asset->file();
        $source = $asset->source();

        if ($file === null && $source === null) {
            throw new InvalidArgumentException(
                'An asset must declare a shipped file, a Vite source, or both'
            );
        }

        if ($file !== null) {
            if (! $this->isAssetable) {
                throw new PackageConfigurationException(
                    "Asset [$file] needs an asset directory. Call hasAssets() before declaring it."
                );
            }

            if (! is_file($this->assetDirectory.DIRECTORY_SEPARATOR.$file)) {
                throw new FileNotFoundException(
                    "Asset file [$file] does not exist in directory [$this->assetDirectory]"
                );
            }
        }

        if ($source !== null && ! is_file($this->path("../$source"))) {
            throw new FileNotFoundException(
                "Vite source [$source] does not exist in package [{$this->path('..')}]"
            );
        }

        foreach ($this->assetEntries as $index => $existing) {
            if ($existing->key() === $asset->key()) {
                $this->assetEntries[$index] = $asset->inheritPresentationFrom($existing);

                return;
            }
        }

        $this->assetEntries[] = $asset;
    }
}
