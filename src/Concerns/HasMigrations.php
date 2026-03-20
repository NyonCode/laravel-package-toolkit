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
     * @var bool Whether to automatically prepend timestamps when publishing migrations without date prefixes.
     */
    private bool $shouldPrependTimestamp = false;

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
     * Supports both timestamped migrations (e.g., 2025_01_01_000000_create_table.php)
     * and timeless migrations (e.g., create_table.php). When publishing, timeless
     * migrations automatically receive a timestamp prefix following Laravel conventions.
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
            $this->detectTimelessMigrations();
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
     * @param  bool  $value  Whether to load migrations when the package is registered
     */
    public function canLoadMigrations(bool $value = true): static
    {
        $this->hasMigrationsOnRun = $value;

        return $this;
    }

    /**
     * Check if any migration files lack a date prefix.
     *
     * Detects the presence of timeless migrations and enables
     * automatic timestamp prepending during publishing.
     */
    private function detectTimelessMigrations(): void
    {
        foreach ($this->migrationFiles as $file) {
            if (! $this->hasDatePrefix($file->getBasename())) {
                $this->shouldPrependTimestamp = true;

                return;
            }
        }
    }

    /**
     * Check if a filename has a Laravel migration date prefix.
     *
     * Matches the standard format: YYYY_MM_DD_HHMMSS_
     *
     * @param  string  $filename  The filename to check
     */
    public function hasDatePrefix(string $filename): bool
    {
        return (bool) preg_match('/^\d{4}_\d{2}_\d{2}_\d{6}_/', $filename);
    }

    /**
     * Whether timestamps should be prepended when publishing migrations.
     */
    public function shouldPrependTimestamp(): bool
    {
        return $this->shouldPrependTimestamp;
    }

    /**
     * Generate a publish mapping for migration files.
     *
     * For timestamped migrations, maps source → destination directly.
     * For timeless migrations, prepends a sequential timestamp to ensure
     * correct execution order.
     *
     * @return array<string, string> Source path => destination path mapping
     */
    public function getMigrationPublishMapping(): array
    {
        $mapping = [];
        $timestamp = now();

        foreach ($this->migrationFiles as $index => $file) {
            $basename = $file->getBasename();

            if ($this->hasDatePrefix($basename)) {
                $mapping[$file->getPathname()] = database_path('migrations/'.$basename);
            } else {
                $datePrefix = $timestamp->addSeconds($index)->format('Y_m_d_His');
                $mapping[$file->getPathname()] = database_path(
                    'migrations/'.$datePrefix.'_'.$basename
                );
            }
        }

        return $mapping;
    }
}
