<?php

namespace NyonCode\LaravelPackageToolkit\Concerns;

use NyonCode\LaravelPackageToolkit\Support\SplFileInfo;
use Exception;

trait HasConfig
{
    use FilesResolver;

    /**
     * Indicates whether the package is configurable.
     *
     * @var bool
     */
    public bool $isConfigurable = false;

    /**
     * The configuration files for the package.
     *
     * @var SplFileInfo[]|null
     */
    protected array|null $configFiles = null;

    /**
     * Get the configuration files.
     *
     * @return SplFileInfo[]|null
     */
    public function configFiles(): array|null
    {
        return $this->configFiles;
    }

    /**
     * Set or validate configuration files.
     *
     * @param string[]|string|null $configFiles The configuration files to validate
     * @param string $directory The directory name where the configuration files are located
     *
     * @throws Exception If the directory does not exist
     *
     * @return static
     */
    public function hasConfig(
        string|array|null $configFiles = null,
        string $directory = 'config'
    ): static {
        $this->configFiles = $this->resolveFiles(
            files: $configFiles,
            directory: $directory,
            type: 'config'
        );

        if( !empty( $this->configFiles )) {
            $this->isConfigurable = true;
        }

        return $this;
    }
}
