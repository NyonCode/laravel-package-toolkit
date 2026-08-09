<?php

namespace NyonCode\LaravelPackageToolkit\Concerns;

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
     * Enable the package's assets.
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

        foreach ($entries as $entry) {
            $this->declareAsset(
                $entry instanceof Asset ? $entry : Asset::make($entry)
            );
        }

        return $this;
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
                $this->assetEntries[$index] = $asset;

                return;
            }
        }

        $this->assetEntries[] = $asset;
    }
}
