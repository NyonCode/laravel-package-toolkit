<?php

namespace NyonCode\LaravelPackageToolkit;

use Illuminate\Support\Str;
use InvalidArgumentException;
use NyonCode\LaravelPackageToolkit\Concerns\FilesResolver;
use NyonCode\LaravelPackageToolkit\Concerns\HasAboutCommand;
use NyonCode\LaravelPackageToolkit\Concerns\HasAssets;
use NyonCode\LaravelPackageToolkit\Concerns\HasCommands;
use NyonCode\LaravelPackageToolkit\Concerns\HasConditionalLoading;
use NyonCode\LaravelPackageToolkit\Concerns\HasConfig;
use NyonCode\LaravelPackageToolkit\Concerns\HasInstallation;
use NyonCode\LaravelPackageToolkit\Concerns\HasMiddleware;
use NyonCode\LaravelPackageToolkit\Concerns\HasMigrations;
use NyonCode\LaravelPackageToolkit\Concerns\HasProviders;
use NyonCode\LaravelPackageToolkit\Concerns\HasRoutes;
use NyonCode\LaravelPackageToolkit\Concerns\HasTranslate;
use NyonCode\LaravelPackageToolkit\Concerns\HasViewComponentNamespaces;
use NyonCode\LaravelPackageToolkit\Concerns\HasViewComponents;
use NyonCode\LaravelPackageToolkit\Concerns\HasViewComposers;
use NyonCode\LaravelPackageToolkit\Concerns\HasViews;
use NyonCode\LaravelPackageToolkit\Concerns\HasViewSharedData;
use NyonCode\LaravelPackageToolkit\Support\Concerns\HasLifecycleHooks;

class Packager
{
    use FilesResolver,
        HasAboutCommand,
        HasAssets,
        HasCommands,
        HasConditionalLoading,
        HasConfig,
        HasInstallation,
        HasMiddleware,
        HasMigrations,
        HasProviders,
        HasRoutes,
        HasTranslate,
        HasViewComponentNamespaces,
        HasViewComponents,
        HasViewComposers,
        HasViews,
        HasViewSharedData,
        HasLifecycleHooks;

    /**
     * @var string The name of the package
     */
    public string $name;

    /**
     * @var string|null The short name of the package
     */
    private ?string $shortName = null;

    /**
     * Set the name of the package.
     *
     * @param  string  $name  The name of the package
     * @return static The current instance
     *
     * @throws InvalidArgumentException If the package name is empty
     */
    public function name(string $name): static
    {
        if (empty(trim($name))) {
            throw new InvalidArgumentException('Package name cannot be empty');
        }

        $this->name = $name;

        return $this;

    }

    /**
     * Get the short name of the package.
     *
     *
     * @return string The short name of the package
     *
     * @throws InvalidArgumentException If the package name is empty
     */
    public function shortName(): string
    {
        if ($this->shortName === null) {
            if (empty($this->name)) {
                throw new InvalidArgumentException('Package name must be set before generating short name');
            }
            $this->shortName = Str::kebab($this->name);
        }

        return $this->shortName;

    }

    /**
     * Set a custom short name for the package with enhanced validation.
     *
     * @param  string  $shortName  The short name to set
     * @return static The current instance
     *
     * @throws InvalidArgumentException If the provided short name is not in the expected format
     */
    public function hasShortName(string $shortName): static
    {
        $trimmed = trim($shortName);

        if ($shortName !== Str::kebab($trimmed)) {
            throw new InvalidArgumentException(
                "The given short name [$shortName] does not match the expected kebab-case format"
            );
        }

        // Validate format (only lowercase letters, numbers, and hyphens)
        if (! preg_match('/^[a-z0-9-]+$/', $trimmed)) {
            throw new InvalidArgumentException(
                'Short name can only contain lowercase letters, numbers, and hyphens'
            );
        }

        $this->shortName = $shortName;

        return $this;
    }
}
