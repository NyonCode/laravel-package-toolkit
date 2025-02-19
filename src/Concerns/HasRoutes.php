<?php

namespace NyonCode\LaravelPackageToolkit\Concerns;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use NyonCode\LaravelPackageToolkit\Support\SplFileInfo;

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
     * @var SplFileInfo[]
     */
    protected array $routeFiles = [];

    /**
     * Get the route files.
     *
     * @return SplFileInfo[]
     */
    public function routeFiles(): array
    {
        return $this->routeFiles;
    }


    /**
     * Set or validate route files.
     *
     * @param string[]|string|null $routeFiles The route files to validate
     * @param string $directory The directory name where the route files are located
     *
     * @throws FileNotFoundException
     *
     * @return static
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
