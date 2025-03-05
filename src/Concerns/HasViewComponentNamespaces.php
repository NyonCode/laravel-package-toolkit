<?php

namespace NyonCode\LaravelPackageToolkit\Concerns;

trait HasViewComponentNamespaces
{
    /**
     * Whether the package has view component namespaces
     *
     * @var bool
     */
    private bool $isViewComponentNamespaces = false;

    /**
     * The view component namespaces
     * @var string[]
     */
    protected array $viewComponentNamespaces = [];

    /**
     * Check if the package has view component namespaces
     *
     * @return bool
     */
    public function isViewComponentNamespaces(): bool
    {
        return $this->isViewComponentNamespaces;
    }

    /**
     * Get the view component namespaces registered in the package.
     *
     * This method returns an associative array of view component namespaces
     * where the key is the namespace prefix and the value is the namespace.
     *
     * @return string[] Array of view component namespaces.
     */
    public function viewComponentNamespaces(): array
    {
        return $this->viewComponentNamespaces;
    }

    /**
     * Registers a single view component namespace with a given prefix.
     *
     * This method allows you to associate a namespace with a prefix,
     * enabling the package to correctly resolve view components within that namespace.
     *
     * @param string $prefix The prefix to associate with the namespace.
     * @param string $namespace The namespace of the view components.
     *
     * @return static
     */
    public function hasComponentNamespace(
        string $prefix,
        string $namespace
    ): static {
        $this->hasComponentNamespaces([$prefix => $namespace]);

        if (!empty($this->viewComponentNamespaces)) {
            $this->isViewComponentNamespaces = true;
        }

        return $this;
    }

    /**
     * Registers multiple view component namespaces.
     *
     * This method takes an associative array of namespace prefixes and namespaces,
     * merging them with the existing view component namespaces. If the namespaces
     * array is not empty, it sets the `isViewComponentNamespaces` flag to true.
     *
     * @param array<string, string> $namespaces An associative array where the key is the namespace prefix
     *                                          and the value is the namespace.
     *
     * @return static
     */
    public function hasComponentNamespaces(array $namespaces): static
    {
        if (!empty($namespaces)) {
            $this->viewComponentNamespaces = array_merge(
                $this->viewComponentNamespaces,
                $namespaces
            );
        }

        if (!empty($this->viewComponentNamespaces)) {
            $this->isViewComponentNamespaces = true;
        }

        return $this;
    }
}