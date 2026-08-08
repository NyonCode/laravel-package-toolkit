<?php

namespace NyonCode\LaravelPackageToolkit\Concerns;

use Exception;
use NyonCode\LaravelPackageToolkit\Support\SplFileInfo;

trait HasFactories
{
    use FilesResolver;

    /**
     * @var bool Indicates whether the package has factory files.
     */
    private bool $isFactorable = false;

    /**
     * @var SplFileInfo[] The factory files for the package.
     */
    protected array $factoryFiles = [];

    /**
     * Indicates whether the package is factorable.
     */
    public function isFactorable(): bool
    {
        return $this->isFactorable;
    }

    /**
     * Get the factory files.
     *
     * @return SplFileInfo[]
     */
    public function factoryFiles(): array
    {
        return $this->factoryFiles;
    }

    /**
     * Set or validate factory files.
     *
     * Factories are a publish-only resource. They are copied into the application's
     * `database/factories` directory, where the application's own `Database\Factories`
     * namespace resolves them. Laravel has no framework hook for loading factories from
     * a package (`loadFactoriesFrom()` was removed in Laravel 8), so a package that
     * wants its own factories discovered without publishing must point at them from the
     * model's `newFactory()` method — the toolkit cannot do it on the package's behalf.
     *
     * @param  array<string>|string|null  $factoryFiles  The factory files to validate
     * @param  string  $directory  The directory name where the factory files are located
     *
     * @throws Exception If any factory file does not exist
     */
    public function hasFactories(
        array|string|null $factoryFiles = null,
        string $directory = '../database/factories'
    ): static {
        $this->factoryFiles = $this->resolveFiles(
            files: $factoryFiles,
            directory: $directory,
            type: 'factory'
        );

        if (! empty($this->factoryFiles)) {
            $this->isFactorable = true;
        }

        return $this;
    }
}
