<?php

namespace NyonCode\LaravelPackageToolkit\Concerns;

use NyonCode\LaravelPackageToolkit\Exceptions\PackagerException;
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
     * @var string[]|SplFileInfo[]|null
     */
    protected array|null $configFiles = null;

    /**
     * Get the configuration files.
     *
     * @return string[]|SplFileInfo[]|null
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
     * @return static
     * @throws Exception If the directory does not exist
     */
    public function hasConfig(
        string|array|null $configFiles = null,
        string $directory = 'config'
    ): static {
        /** @var array<string|SplFileInfo> $configFilesInfo */
        $configFilesInfo = [];

        if (!empty($configFiles)) {
            if (!is_array($configFiles)) {
                $configFiles = [$configFiles];
            }

            foreach ($configFiles as $configFile) {
                $filePath = $this->resolveFilePath($configFile, $directory);

                if (empty($filePath) && !is_file($filePath)) {
                    throw PackagerException::fileNotExist($configFile, 'config'
                    );
                }

                $configFilesInfo[] = $this->getFileInfo(
                    $this->path($configFile)
                );
            }

            /** @var array<string|SplFileInfo> $configFilesInfo */
            $this->configFiles = $configFilesInfo;
        } else {
            $this->configFiles = $this->autoloadFiles($directory);
        }

        $this->isConfigurable = true;

        return $this;
    }

}