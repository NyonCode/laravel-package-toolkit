<?php

namespace NyonCode\LaravelPackageToolkit\Concerns;

use Illuminate\Support\Facades\File;
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
     * Enable the package's assets.
     *
     * @param  string  $directory  The directory name where the assets are located
     * @param  bool  $mirror  Whether to keep the assets mirrored into `public/vendor/<short-name>`
     *
     * @throws DirectoryNotFoundException if the directory does not exist
     */
    public function hasAssets(string $directory = 'dist', bool $mirror = true): static
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

        return $this;
    }
}
