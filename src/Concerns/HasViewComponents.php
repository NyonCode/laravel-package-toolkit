<?php

namespace NyonCode\LaravelPackageToolkit\Concerns;

trait HasViewComponents
{
    /**
     * Whether the package has view components
     *
     * @var bool
     */
    public bool $isViewComponents = false;

    /**
     * Whether the package has view component namespaces
     *
     * @var bool
     */
    public bool $isViewComponentNamespaces = false;

    /**
     * The view components
     *
     * @var string[]
     */
    protected array $viewComponents = [];

    /**
     * The view component namespaces
     * @var string[]
     */
    protected array $viewComponentNamespaces = [];

    /**
     * Get the view components registered in the package.
     *
     * @return string[] Array of view components.
     */
    public function viewComponents(): array
    {
        return $this->viewComponents;
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
     * Registers a single view component.
     *
     * Validates and registers the given view component in the package.
     *
     * @param string $prefix The namespace prefix for the component.
     * @param string $componentClass The component class to register.
     * @param string $alias The alias for the component.
     *
     * @return static
     */
    public function hasComponent(string $prefix, string $componentClass, string $alias = ''): static
    {
        $this->hasComponents($prefix, $componentClass);

        if (!empty($alias)) {
            $this->hasComponents($prefix, [$alias => $componentClass]);
        }

        return $this;
    }


    /**
     * Set view components for the package.
     *
     * Validates and registers the given view components.
     *
     * @param string $prefix
     * @param string|string[] $components Array of view components with names as keys and component objects as
     *                                          values.
     *
     * @return static
     */
    public function hasComponents(
        string $prefix,
        array|string $components
    ): static {
        if (!is_array($components)) {
            $components = [$components];
        }

        foreach ($components as $alias => $component) {
            $this->viewComponents[] = [
                'component' => $component,
                'alias' => null,
                'prefix' => $prefix,
            ];

            if (!empty($alias)) {
                $this->viewComponents[] = [
                    'component' => $component,
                    'alias' => $alias,
                    'prefix' => $prefix,
                ];
            }
        }

        if (!empty($this->viewComponents)) {
            $this->isViewComponents = true;
        }

        return $this;
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
