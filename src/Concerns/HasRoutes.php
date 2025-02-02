<?php

namespace NyonCode\LaravelPackageToolkit\Concerns;

use NyonCode\LaravelPackageToolkit\Support\SplFileInfo;
use Exception;

trait HasRoutes
{
    use FilesResolver;

    /**
     * Indicates whether the package has route files.
     *
     * @var bool
     */
    public bool $isRoutable = false;

    /**
     * The route files for the package.
     *
     * @var string[]|SplFileInfo[]|null
     */
    protected array|null $routeFiles = null;

    /**
     * Get the route files.
     *
     * @return string[]|SplFileInfo[]|null
     */
    public function routeFiles(): array|null
    {
        return $this->routeFiles;
    }

    /**
     * Set or validate route files.
     *
     * @param string[]|null $routeFiles The route files to validate
     * @param string $directory The directory name where the route files are located
     * @return static
     * @throws Exception If the directory does not exist
     */
    public function hasRoutes(
        array|string|null $routeFiles = null,
        string $directory = 'routes'
    ): static {
        $this->routeFiles = $this->resolveFiles(
            files: $routeFiles,
            directory: $directory,
            type: 'route'
        );

        if( !empty( $this->routeFiles )) {
            $this->isRoutable = true;
        }

        return $this;
    }
}
