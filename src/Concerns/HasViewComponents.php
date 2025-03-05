<?php

namespace NyonCode\LaravelPackageToolkit\Concerns;

use ReflectionClass;
use ReflectionException;

trait HasViewComponents
{
    /**
     * Whether the package has view components
     *
     * @var bool
     */
    private bool $isViewComponents = false;

    /**
     * The view components for the package.
     *
     * @var array
     */
    protected array $viewComponents = [];

    /**
     * The view component paths for the package.
     *
     * @var array
     */
    private array $viewComponentPaths = [];

    /**
     * Check if the package has view components
     *
     * @return bool
     */
    public function isViewComponents(): bool
    {
        return $this->isViewComponents;
    }

    /**
     * Get the view components registered in the package.
     *
     * @return array Array of view components.
     */
    public function viewComponents(): array
    {
        return $this->viewComponents;
    }

    /**
     * Get the view component paths registered in the package.
     *
     * This method returns an array of paths where the view components
     * are located within the package.
     *
     * @return array Array of view component paths.
     */
    public function viewComponentPaths(): array
    {
        return $this->viewComponentPaths;
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
     * @throws ReflectionException
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
     *
     * @throws ReflectionException
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

            $componentsReflected = new ReflectionClass($component);
            $componentsDirname = dirname($componentsReflected->getFileName());
            if(!in_array($componentsDirname, $this->viewComponentPaths)) {
                $this->viewComponentPaths[] = $componentsDirname;
            }
        }

        if (!empty($this->viewComponents)) {
            $this->isViewComponents = true;
        }

        return $this;
    }
}
