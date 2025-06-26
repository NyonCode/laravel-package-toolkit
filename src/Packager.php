<?php

declare(strict_types=1);

namespace NyonCode\LaravelPackageToolkit;

use Illuminate\Support\Str;
use InvalidArgumentException;
use NyonCode\LaravelPackageToolkit\Concerns\FilesResolver;
use NyonCode\LaravelPackageToolkit\Concerns\HasAboutCommand;
use NyonCode\LaravelPackageToolkit\Concerns\HasAssets;
use NyonCode\LaravelPackageToolkit\Concerns\HasCommands;
use NyonCode\LaravelPackageToolkit\Concerns\HasConfig;
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

class Packager
{
    use FilesResolver,
        HasAboutCommand,
        HasAssets,
        HasCommands,
        HasConfig,
        HasMiddleware,
        HasMigrations,
        HasProviders,
        HasRoutes,
        HasTranslate,
        HasViewComponentNamespaces,
        HasViewComponents,
        HasViewComposers,
        HasViews,
        HasViewSharedData;

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
     */
    public function name(string $name): static
    {
        $this->name = $name;

        return $this;

    }

    /**
     * Get the short name of the package.
     */
    public function shortName(): string
    {
        return $this->shortName ??= Str::kebab($this->name);
    }

    /**
     * Set a custom short name for the package.
     *
     * @param  string  $shortName  The short name to set
     *
     * @throws InvalidArgumentException If the provided short name is not in the expected format
     */
    public function hasShortName(string $shortName): static
    {
        if ($shortName !== Str::kebab($shortName)) {
            throw new InvalidArgumentException(
                "The given namespace [$shortName] does not match the expected format."
            );
        }

        $this->shortName = $shortName;

        return $this;
    }
}
