<?php

namespace NyonCode\LaravelPackageToolkit\Concerns;

use ReflectionClass;
use ReflectionException;

trait HasViewComponents
{
    /**
     * @var bool Whether the package has view components
     */
    private bool $isViewComponentized = false;

    /**
     * @var array The view components for the package.
     */
    protected array $viewComponents = [];

    /**
     * @var array The view component paths for the package.
     */
    private array $viewComponentPaths = [];

    /**
     * Check if the package has view components
     */
    public function isViewComponentized(): bool
    {
        return $this->isViewComponentized;
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
     * @param  string  $prefix  The namespace prefix for the component.
     * @param  string  $componentClass  The component class to register.
     * @param  string  $alias  The alias for the component.
     *
     * @throws ReflectionException
     */
    public function hasComponent(string $prefix, string $componentClass, string $alias = ''): static
    {
        if (! empty($alias)) {
            return $this->hasComponents($prefix, [$alias => $componentClass]);
        }

        return $this->hasComponents($prefix, $componentClass);
    }

    /**
     * Set view components for the package.
     *
     * Validates and registers the given view components.
     *
     * @param  string|string[]  $components  Array of view components with names as keys and component objects as
     *
     * @throws ReflectionException
     */
    public function hasComponents(
        string $prefix,
        array|string $components
    ): static {
        if (! is_array($components)) {
            $components = [$components];
        }

        foreach ($components as $alias => $component) {
            $this->viewComponents[] = [
                'component' => $component,
                'alias' => null,
                'prefix' => $prefix,
            ];

            // Only string keys are aliases; numeric keys come from list-style arrays
            if (is_string($alias) && $alias !== '') {
                $this->viewComponents[] = [
                    'component' => $component,
                    'alias' => $alias,
                    'prefix' => $prefix,
                ];
            }

            $componentsReflected = new ReflectionClass($component);
            $componentsDirname = dirname($componentsReflected->getFileName());
            if (! in_array($componentsDirname, $this->viewComponentPaths)) {
                $this->viewComponentPaths[] = $componentsDirname;
            }
        }

        if (! empty($this->viewComponents)) {
            $this->isViewComponentized = true;
        }

        return $this;
    }
}
