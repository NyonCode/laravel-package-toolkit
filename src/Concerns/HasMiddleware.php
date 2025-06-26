<?php

namespace NyonCode\LaravelPackageToolkit\Concerns;

trait HasMiddleware
{
    /**
     * @var bool Whether the package has middleware
     */
    private bool $isSetMiddlewareAliases = false;

    /**
     * @var array The middleware aliases
     */
    protected array $middlewareAliases = [];

    /**
     * @var bool Whether the package has middleware groups
     */
    private bool $isSetMiddlewareGroups = false;

    /**
     * @var array The middleware groups
     */
    protected array $middlewareGroups = [];

    /**
     * @var bool Whether the package has global middlewares
     */
    private bool $isSetMiddlewareGlobals = false;

    /**
     * @var array The global middlewares
     */
    protected array $middlewareGlobals = [];

    /**
     * Add middleware aliases
     *
     * @return $this
     */
    public function hasMiddlewareAliases(array $aliases): static
    {
        $this->middlewareAliases = array_merge($this->middlewareAliases, $aliases);

        if (! empty($this->middlewareAliases)) {
            $this->isSetMiddlewareAliases = true;
        }

        return $this;
    }

    /**
     * Add middleware groups
     *
     * @return $this
     */
    public function hasMiddlewareGroups(array $groups): static
    {
        foreach ($groups as $group => $middlewares) {
            $this->middlewareGroups[$group] = array_merge(
                $this->middlewareGroups[$group] ?? [],
                (array) $middlewares
            );
        }
        if (! empty($this->middlewareGroups)) {
            $this->isSetMiddlewareGroups = true;
        }

        return $this;
    }

    /**
     * Add global middlewares
     *
     * @return $this
     */
    public function hasMiddlewareGlobals(array $middlewares): static
    {
        $this->middlewareGlobals = array_merge($this->middlewareGlobals, $middlewares);

        if (! empty($this->middlewareGlobals)) {
            $this->isSetMiddlewareGlobals = true;
        }

        return $this;
    }

    /**
     * Get middleware aliases
     */
    public function getMiddlewareAliases(): array
    {
        return $this->middlewareAliases;
    }

    /**
     * Get middleware groups
     */
    public function getMiddlewareGroups(): array
    {
        return $this->middlewareGroups;
    }

    /**
     * Get global middlewares
     */
    public function getMiddlewareGlobals(): array
    {
        return $this->middlewareGlobals;
    }

    /**
     * Check if the package has middleware aliases
     */
    public function isSetMiddlewareAliases(): bool
    {
        return $this->isSetMiddlewareAliases;
    }

    /**
     * Check if the package has middleware groups
     */
    public function isSetMiddlewareGroups(): bool
    {
        return $this->isSetMiddlewareGroups;
    }

    /**
     * Check if the package has global middlewares
     */
    public function isSetMiddlewareGlobals(): bool
    {
        return $this->isSetMiddlewareGlobals;
    }
}
