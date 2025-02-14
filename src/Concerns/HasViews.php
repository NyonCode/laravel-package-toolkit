<?php

namespace NyonCode\LaravelPackageToolkit\Concerns;

use Symfony\Component\Finder\Exception\DirectoryNotFoundException;

trait HasViews
{
    use FilesResolver;
    /**
     * Whether the package has views
     *
     * @var bool
     */
    public bool $isView = false;

    /**
     * The path to the views
     *
     * @var string
     */
    protected string $viewsPath = '';


    /**
     * Get the path to the views directory.
     *
     * @return string The path to the views directory.
     */
    public function views(): string
    {
        return $this->viewsPath;
    }

    /**
     * Set or variable views folder
     *
     * @param string|null $viewsPath The path to the views files
     * @param string      $directory The directory name where the views files are located
     *
     * @throw DirectoryNotFoundException
     *
     * @return static
     */
    public function hasViews(
        string|null $viewsPath = null,
        string $directory = '../resources/views'
    ): static {
        if (!empty($viewsPath)) {
            if (!is_dir($this->path($viewsPath))) {
                throw new DirectoryNotFoundException(
                    "Directory [$viewsPath] does not exist"
                );
            }

            $this->viewsPath = $viewsPath;
        } else {
            $this->viewsPath = $this->path($directory);
        }

        if (!empty($this->viewsPath)) {
            $this->isView = true;
        }

        return $this;
    }
}
