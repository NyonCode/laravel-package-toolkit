<?php

namespace NyonCode\LaravelPackageToolkit\Concerns;

use Symfony\Component\Finder\Exception\DirectoryNotFoundException;

trait HasViews
{
    use FilesResolver;

    /**
     * @var bool Whether the package has views
     */
    private bool $isViewable = false;

    /**
     * @var string The path to the views
     */
    protected string $viewsPath = '';
    protected ?string $viewNamespace = null;


    public function isViewable(): bool
    {
        return $this->isViewable;
    }

    /**
     * Get the path to the view's directory.
     *
     * @return string The path to the view's directory.
     */
    public function views(): string
    {
        return $this->viewsPath;
    }

    /**
     * Configure or set the path to the view templates for the package or component.
     *
     * This method allows you to define a custom path where the Blade views are located.
     * If no path is provided, a default relative directory (e.g., `../resources/views`) is used.
     * It also allows optional registration of a namespace for easier referencing in views.
     *
     * Example usage:
     * ```php
     * $this->hasViews(__DIR__ . '/../resources/views', 'views', 'mypackage');
     * ```
     *
     * @param  string|null  $viewsPath   Absolute or relative path to the views directory.
     *                                   If `null`, the `$directory` parameter will be used.
     * @param  string       $directory   Default relative directory path used when `$viewsPath` is not set.
     * @param  string|null  $namespace   Optional view namespace (e.g., `'mypackage'`) for namespaced Blade includes.
     *
     * @throws DirectoryNotFoundException
     *         Thrown when the provided views directory does not exist.
     *
     * @return static  Returns the current instance for method chaining.
     */
    public function hasViews(
        ?string $viewsPath = null,
        string $directory = '../resources/views',
        ?string $namespace = null
    ): static {
        if (! empty($viewsPath)) {
            if (! is_dir($this->path($viewsPath))) {
                throw new DirectoryNotFoundException(
                    "Directory [$viewsPath] does not exist"
                );
            }

            $this->viewsPath = $viewsPath;
        } else {
            $this->viewsPath = $this->path($directory);
        }

        if (! empty($namespace)) {
            $this->viewNamespace = $namespace;
        }

        if (! empty($this->viewsPath)) {
            $this->isViewable = true;
        }

        return $this;
    }
}
