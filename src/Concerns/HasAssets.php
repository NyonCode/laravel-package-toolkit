<?php

namespace NyonCode\LaravelPackageToolkit\Concerns;

use File;
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
     * Enable the package's assets.
     *
     * @param  string  $directory  The directory name where the assets are located
     *
     * @throws DirectoryNotFoundException if the directory does not exist
     */
    public function hasAssets(string $directory = 'public'): static
    {
        $path = $this->path("../$directory");

        if (! File::isDirectory($path)) {
            throw new DirectoryNotFoundException(
                "Directory [$path] does not exist"
            );
        }
        $this->assetDirectory = $this->path($directory);
        $this->isAssetable = true;

        return $this;
    }
}
