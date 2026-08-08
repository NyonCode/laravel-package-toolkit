<?php

namespace NyonCode\LaravelPackageToolkit\Concerns;

use Exception;
use NyonCode\LaravelPackageToolkit\Support\SplFileInfo;

trait HasStubs
{
    use FilesResolver;

    /**
     * @var bool Indicates whether the package has stub files.
     */
    private bool $isStubbable = false;

    /**
     * @var SplFileInfo[] The stub files for the package.
     */
    protected array $stubFiles = [];

    /**
     * Indicates whether the package is stubbable.
     */
    public function isStubbable(): bool
    {
        return $this->isStubbable;
    }

    /**
     * Get the stub files.
     *
     * @return SplFileInfo[]
     */
    public function stubFiles(): array
    {
        return $this->stubFiles;
    }

    /**
     * Set or validate stub files.
     *
     * Stubs are a publish-only resource, published to `stubs/<package-short-name>` so a
     * consumer can customise what the package's generator commands scaffold. The short
     * name subdirectory keeps them apart from the framework's own `stub:publish` output
     * and from every other package's stubs, which share a single flat `stubs/` directory.
     *
     * Extensions are preserved: a `.stub` file is published as `.stub`.
     *
     * @param  array<string>|string|null  $stubFiles  The stub files to validate
     * @param  string  $directory  The directory name where the stub files are located
     *
     * @throws Exception If any stub file does not exist
     */
    public function hasStubs(
        array|string|null $stubFiles = null,
        string $directory = '../stubs'
    ): static {
        $this->stubFiles = $this->resolveFiles(
            files: $stubFiles,
            directory: $directory,
            type: 'stub'
        );

        if (! empty($this->stubFiles)) {
            $this->isStubbable = true;
        }

        return $this;
    }
}
