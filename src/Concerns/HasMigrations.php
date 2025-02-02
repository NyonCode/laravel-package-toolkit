<?php

namespace NyonCode\LaravelPackageToolkit\Concerns;

use NyonCode\LaravelPackageToolkit\Support\SplFileInfo;
use Exception;

trait HasMigrations
{
    use FilesResolver;
    /**
     * Indicates whether the package has migration files.
     *
     * @var bool
     */
    public bool $isMigratable = false;

    /**
     * Run migrations without publishing them.
     *
     * @var bool
     */
    public bool $hasMigrationsOnRun = false;

    /**
     * The migration files for the package.
     *
     * @var string[]|SplFileInfo[]|null
     */
    protected array|null $migrationFiles = null;

    /**
     * Set or validate migration files.
     *
     * @param array<string>|null $migrationFiles The migration files to validate
     * @param string $directory The directory name where the migration files are located
     *
     * @throws Exception If any other error occurs
     *
     * @return static
     */
    public function hasMigrations(
        array|null $migrationFiles = null,
        string $directory = 'database/migrations'
    ): static {

//        /** @var array<string|SplFileInfo> $migrationFilesInfo */
//        $migrationFilesInfo = [];
//
//        if (!empty($migrationFiles)) {
//            if (!is_array($migrationFiles)) {
//                $migrationFiles = [$migrationFiles];
//            }
//
//            foreach ($migrationFiles as $migrationFile) {
//                if (!file_exists($migrationFile)) {
//                    throw PackagerException::fileNotExist(
//                        file: $migrationFile,
//                        type: 'migration'
//                    );
//                }
//
//                $migrationFilesInfo[] = $this->getFileInfo(
//                    $this->path($migrationFile)
//                );
//            }
//
//            /** @var array<string|SplFileInfo> $migrationFilesInfo */
//            $this->migrationFiles = $migrationFilesInfo;
//        } else {
//            $this->migrationFiles = $this->autoloadFiles($directory);
//        }

        $this->migrationFiles = $this->resolveFiles(
            files: $migrationFiles,
            directory: $directory,
            type: 'migration'
        );

        $this->isMigratable = true;

        return $this;
    }

    /**
     * Get the migration files.
     *
     * @return string[]|SplFileInfo[]|null
     */
    public function migrationFiles(): array|null
    {
        return $this->migrationFiles;
    }

    /**
     * Enable or disable loading of migrations when the package is registered.
     *
     * Set to `false` to prevent migrations from being loaded when the package is registered.
     * Set to `true` to enable loading of migrations when the package is registered.
     *
     * @param bool $value Whether to load migrations when the package is registered
     * @return static
     */
    public function canLoadMigrations(bool $value = true): static
    {
        $this->hasMigrationsOnRun = $value;

        return $this;
    }
}