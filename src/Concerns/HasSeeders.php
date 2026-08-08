<?php

namespace NyonCode\LaravelPackageToolkit\Concerns;

use Exception;
use NyonCode\LaravelPackageToolkit\Support\SplFileInfo;

trait HasSeeders
{
    use FilesResolver;

    /**
     * @var bool Indicates whether the package has seeder files.
     */
    private bool $isSeedable = false;

    /**
     * @var SplFileInfo[] The seeder files for the package.
     */
    protected array $seederFiles = [];

    /**
     * Indicates whether the package is seedable.
     */
    public function isSeedable(): bool
    {
        return $this->isSeedable;
    }

    /**
     * Get the seeder files.
     *
     * @return SplFileInfo[]
     */
    public function seederFiles(): array
    {
        return $this->seederFiles;
    }

    /**
     * Set or validate seeder files.
     *
     * Seeders are a publish-only resource: they are copied into the application's
     * `database/seeders` directory, where the application's own `Database\Seeders`
     * namespace resolves them, so `db:seed --class=...` works without further wiring.
     *
     * @param  array<string>|string|null  $seederFiles  The seeder files to validate
     * @param  string  $directory  The directory name where the seeder files are located
     *
     * @throws Exception If any seeder file does not exist
     */
    public function hasSeeders(
        array|string|null $seederFiles = null,
        string $directory = '../database/seeders'
    ): static {
        $this->seederFiles = $this->resolveFiles(
            files: $seederFiles,
            directory: $directory,
            type: 'seeder'
        );

        if (! empty($this->seederFiles)) {
            $this->isSeedable = true;
        }

        return $this;
    }
}
