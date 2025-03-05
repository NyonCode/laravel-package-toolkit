<?php declare(strict_types=1);

namespace NyonCode\LaravelPackageToolkit;

use Illuminate\Support\Str;
use InvalidArgumentException;
use NyonCode\LaravelPackageToolkit\Concerns\FilesResolver;
use NyonCode\LaravelPackageToolkit\Concerns\HasAboutCommand;
use NyonCode\LaravelPackageToolkit\Concerns\HasAssets;
use NyonCode\LaravelPackageToolkit\Concerns\HasCommands;
use NyonCode\LaravelPackageToolkit\Concerns\HasConfig;
use NyonCode\LaravelPackageToolkit\Concerns\HasMigrations;
use NyonCode\LaravelPackageToolkit\Concerns\HasRoutes;
use NyonCode\LaravelPackageToolkit\Concerns\HasTranslate;
use NyonCode\LaravelPackageToolkit\Concerns\HasViewComposers;
use NyonCode\LaravelPackageToolkit\Concerns\HasViews;
use NyonCode\LaravelPackageToolkit\Concerns\HasViewComponents;
use NyonCode\LaravelPackageToolkit\Concerns\HasViewComponentNamespaces;
use NyonCode\LaravelPackageToolkit\Concerns\HasViewSharedData;

class Packager
{
    use FilesResolver;
    use HasAssets;
    use HasAboutCommand;
    use HasCommands;
    use HasConfig;
    use HasMigrations;
    use HasRoutes;
    use HasTranslate;
    use HasViews;
    use HasViewComponents;
    use HasViewComponentNamespaces;
    use HasViewComposers;
    use HasViewSharedData;

    public string $name;

    /**
     * The short name of the package, or null if not set.
     *
     * @var string|null
     */
    private string|null $shortName = null;

    /**
     * Set the name of the package.
     *
     * @param string $name The name of the package
     * @return static
     */
    public function name(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Get the short name of the package.
     *
     * @return string
     */
    public function shortName(): string
    {
        return $this->shortName ??= Str::kebab($this->name);
    }

    /**
     * Set a custom short name for the package.
     *
     * @param string $shortName The short name to set
     *
     * @throws InvalidArgumentException If the provided short name is not in the expected format
     *
     * @return static
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
