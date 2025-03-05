<?php

namespace NyonCode\LaravelPackageToolkit\Concerns;

use Closure;

trait HasViewComposers
{
    private bool $isViewComposable = false;

    protected array $viewComposers = [];

    /**
     * Checks if the package has registered any view composers.
     *
     * @return bool Whether the package has registered any view composers.
     */
    public function isViewComposable(): bool
    {
        return $this->isViewComposable;
    }

    /**
     * Get the view composers registered in the package.
     *
     * This method returns an array of view composers which have been registered
     * in the package. View composers can be used to bind data to views when
     * they are rendered.
     *
     * @return array The array of registered view composers.
     */
    public function viewComposers(): array
    {
        return $this->viewComposers;
    }

    /**
     * Registers a single view composer for the package.
     *
     * This method accepts a view composer, which can be a string or a Closure,
     * and registers it by calling the `hasViewComposers` method.
     *
     * @param string|array $views The views to register the composer for.
     * @param string|Closure $composer The view composer to register.
     *
     * @return static
     */
    public function hasViewComposer(string|array $views, string|Closure $composer): static
    {
        if (is_array($views)) {
            foreach ($views as $view) {
                $this->hasViewComposers([$view => $composer]);
            }
            return $this;
        }
        $this->hasViewComposers([$views => $composer]);

        return $this;
    }

    /**
     * Registers multiple view composers for the package.
     *
     * This method accepts an array of view composers, where each composer can be
     * a string or a Closure, and merges them with the existing view composers.
     * If the array of composers is not empty, it sets the `isViewComposable` flag
     * to true.
     *
     * @param array<string, string|Closure> $composers The view composers to register.
     *
     * @return static
     */
    private function hasViewComposers(array $composers): static
    {
        foreach ($composers as $view => $composer) {
            $this->viewComposers[$view] = $composer;
        }

        if(!empty($this->viewComposers)) {
            $this->isViewComposable = true;
        }

        return $this;
    }
}