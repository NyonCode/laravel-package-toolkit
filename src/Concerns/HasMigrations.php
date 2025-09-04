<?php

namespace NyonCode\LaravelPackageToolkit\Concerns;

use Exception;
use NyonCode\LaravelPackageToolkit\Support\SplFileInfo;

trait HasMigrations
{
    use FilesResolver;

    /**
     * @var bool Indicates whether the package has migration files.
     */
    private bool $isMigratable = false;

    /**
     * @var bool Run migrations without publishing them.
     */
    public bool $hasMigrationsOnRun = false;

    /**
     * @var SplFileInfo[] The migration files for the package.
     */
    protected array $migrationFiles = [];

    /**
     * Indicates whether the package is migratable.
     */
    public function isMigratable(): bool
    {
        return $this->isMigratable;
    }

    /**
     * Set or validate migration files.
     *
     * @param  array<string>|null  $migrationFiles  The migration files to validate
     * @param  string  $directory  The directory name where the migration files are located
     *
     * @throws Exception If any other error occurs
     */
    public function hasMigrations(
        ?array $migrationFiles = null,
        string $directory = '../database/migrations'
    ): static {
        $this->migrationFiles = $this->resolveFiles(
            files: $migrationFiles,
            directory: $directory,
            type: 'migration'
        );

        if (! empty($this->migrationFiles)) {
            $this->isMigratable = true;
        }

        return $this;
    }

    /**
     * Get the migration files.
     *
     * @return SplFileInfo[]
     */
    public function migrationFiles(): array
    {
        return $this->migrationFiles;
    }

    /**
     * Enable or disable loading of migrations when the package is registered.
     *
     * Set to `false` to prevent migrations from being loaded when the package is registered.
     * Set to `true` to enable loading of migrations when the package is registered.
     *
     * @param  bool  $value  Whether to load migrations when the package is
     */
    public function canLoadMigrations(bool $value = true): static
    {
        $this->hasMigrationsOnRun = $value;

        return $this;
    }
}
