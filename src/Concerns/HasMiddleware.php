<?php

namespace NyonCode\LaravelPackageToolkit\Concerns;

trait HasMiddleware
{
    private bool $isSetMiddlewareAliases = false;
    protected array $middlewareAliases = [];
    private bool $isSetMiddlewareGroups  = false;
    protected array $middlewareGroups = [];
    private bool $isSetMiddlewareGlobals = false;
    protected array $middlewareGlobals = [];

    /**
     * Add middleware aliases
     *
     * @param array $aliases
     * @return $this
     */
    public function hasMiddlewareAliases(array $aliases): static
    {
        $this->middlewareAliases = array_merge($this->middlewareAliases, $aliases);

        if( !empty( $this->middlewareAliases )) {
            $this->isSetMiddlewareAliases = true;
        }

        return $this;
    }

    /**
     * Add middleware groups
     *
     * @param array $groups
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
        if( !empty( $this->middlewareGroups )) {
            $this->isSetMiddlewareGroups = true;
        }

        return $this;
    }

    /**
     * Add global middlewares
     *
     * @param array $middlewares
     * @return $this
     */
    public function hasMiddlewareGlobals(array $middlewares): static
    {
        $this->middlewareGlobals = array_merge($this->middlewareGlobals, $middlewares);

        if( !empty( $this->middlewareGlobals )) {
            $this->isSetMiddlewareGlobals = true;
        }

        return $this;
    }

    /**
     * Get middleware aliases
     *
     * @return array
     */
    public function getMiddlewareAliases(): array
    {
        return $this->middlewareAliases;
    }

    /**
     * Get middleware groups
     *
     * @return array
     */
    public function getMiddlewareGroups(): array
    {
        return $this->middlewareGroups;
    }

    /**
     * Get global middlewares
     *
     * @return array
     */
    public function getMiddlewareGlobals(): array
    {
        return $this->middlewareGlobals;
    }

    /**
     * Check if the package has middleware aliases
     *
     * @return bool
     */
    public function isSetMiddlewareAliases(): bool
    {
        return $this->isSetMiddlewareAliases;
    }

    /**
     * Check if the package has middleware groups
     *
     * @return bool
     */
    public function isSetMiddlewareGroups(): bool
    {
        return $this->isSetMiddlewareGroups;
    }

    /**
     * Check if the package has global middlewares
     *
     * @return bool
     */
    public function isSetMiddlewareGlobals(): bool
    {
        return $this->isSetMiddlewareGlobals;
    }
}