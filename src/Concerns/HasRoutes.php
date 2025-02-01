<?php

namespace NyonCode\LaravelPackageToolkit\Concerns;

use NyonCode\LaravelPackageToolkit\Exceptions\PackagerException;
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
    public function hasRoutes(array|string|null $routeFiles = null, string $directory = 'routes'): static {

        /** @var array<string|SplFileInfo> $routeFilesInfo */
        $routeFilesInfo = [];

        if (!empty($routeFiles)) {
            if (!is_array($routeFiles)) {
                $routeFiles = [$routeFiles];
            }

            foreach ($routeFiles as $routeFile) {
                $filePath = $this->resolveFilePath($routeFile, $directory);

                if (empty($filePath) && !is_file($filePath)) {
                    throw PackagerException::fileNotExist($routeFile, 'route');
                }

                $routeFilesInfo[] = $this->getFileInfo($filePath);
            }

            /** @var array<string|SplFileInfo> $routeFilesInfo */
            $this->routeFiles = $routeFilesInfo;
        } else {
            $this->routeFiles = $this->autoloadFiles($directory);
        }

        $this->isRoutable = true;

        return $this;
    }

}