<?php

namespace NyonCode\LaravelPackageToolkit\Concerns;


use Illuminate\Support\Facades\File;
use NyonCode\LaravelPackageToolkit\Exceptions\InvalidComponentClass;
use NyonCode\LaravelPackageToolkit\Exceptions\InvalidComponentName;
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
     * @var string|null
     */
    protected string|null $viewsPath = null;

    /**
     * Whether the package has view components
     *
     * @var bool
     */
    public bool $isViewComponent = false;

    /**
     * The view components
     *
     * @var array<string, string>
     */
    protected array $viewComponents = [];

    /**
     * Get the path to the views directory.
     *
     * @return string|null The path to the views directory.
     */
    public function views(): string|null
    {
        return $this->viewsPath;
    }

    /**
     * Set or variable views folder
     *
     * @param string|null $viewsPath The path to the views files
     * @param string      $directory The directory name where the views files are located
     *
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
            if (File::isDirectory($this->path($viewsPath))) {
                $this->viewsPath = $this->path($viewsPath);
            } else {
                throw new DirectoryNotFoundException("Directory [$viewsPath] does not exist");
            }
        } else {
            $this->viewsPath = $this->path($directory);
        }

        $this->isView = true;
        return $this;
    }

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
     * Set view components for the package.
     *
     * Validates and registers the given view components.
     *
     * @param array<string> $components Array of view components with names as keys and component objects as
     *                                          values.
     *
     * @throws InvalidComponentClass
     * @throws InvalidComponentName
     *
     * @return static
     */
    public function hasComponents(array $components): static
    {
        if (!empty($components)) {
            if ($this->validateComponents($components)) {
                $this->viewComponents = $components;
                $this->isViewComponent = true;
            }
        }

        return $this;
    }

    /**
     * Validates an array of components by checking their names and classes.
     *
     * This method iterates through each component in the provided array, ensuring that:
     *  - Each key (component name) is a string.
     *  - Each component value is an object.
     *
     * If any component fails these validations, a `PackagerException` is thrown with a
     * detailed error message.
     *
     * @param array<string|object> $components Associative array of components where the key is the component
     *                          name and the value is the component class object.
     *

     * @throws InvalidComponentName
     * @throws InvalidComponentClass
     * @return bool Returns true if all components pass validation.
     *
     */
    public function validateComponents(array $components): bool
    {
        // #TODO Verify component is object

        foreach ($components as $name => $component) {
            if (!is_string($name)) {
                throw new InvalidComponentName("Invalid name [$name]. The name must be a string.");
            }

            if (!is_object($component)) {
                throw new InvalidComponentClass("Invalid name [$name]. The name must be a string.");

            }
        }

        return true;
    }
}