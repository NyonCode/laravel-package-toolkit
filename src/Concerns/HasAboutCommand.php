<?php

namespace NyonCode\LaravelPackageToolkit\Concerns;

use Closure;
use Composer\InstalledVersions;
use Illuminate\Foundation\Console\AboutCommand;
use Throwable;

trait HasAboutCommand
{
    /**
     * @var array<string, mixed> The data from composer.json
     */
    private array $composerData = [];

    /**
     * @var string The version of the package
     */
    public string $version = '';

    /**
     * @var bool Whether the package is aboutable
     */
    private bool $isAboutable = false;

    /**
     * @var array<string, string|Closure> Additional data for the AboutCommand
     */
    private array $aboutData = [];

    /**
     * Retrieves a specific value from the composer.json file by key name.
     *
     * Read with `json_decode` rather than Composer's own `JsonFile`, and that
     * is the whole reason this package no longer requires `composer/composer`.
     * One class, used once, to read one file — and requiring it put the entire
     * Composer application into the production dependencies of every package
     * built on this toolkit, along with a `laravel/pint` nothing used and an
     * `ext-readline` that failed the install outright wherever the extension is
     * absent.
     *
     * `InstalledVersions` and `Autoload\ClassLoader` stay: they ship with the
     * generated autoloader that every Composer install has, which is what
     * `composer-runtime-api` declares. They never needed the package either.
     *
     * A malformed file yields null here rather than an exception. This feeds
     * `php artisan about`, and a broken `composer.json` is not worth turning a
     * diagnostic command into a fatal one.
     *
     * @param  string  $keyName  The key to retrieve from composer.json.
     */
    private function getComposerValue(string $keyName): ?string
    {
        $path = $this->path('/../composer.json');

        if (is_file($path) && is_readable($path)) {
            $contents = file_get_contents($path);
            $data = $contents === false ? null : json_decode($contents, true);

            $this->composerData = is_array($data) ? $data : [];
        }

        $value = $this->composerData[$keyName] ?? null;

        return is_string($value) ? $value : null;
    }

    /** Retrieves the version of the package. */
    public function getVersion(): ?string
    {
        if (! empty($this->version)) {
            return $this->version;
        }

        $packageName = $this->getComposerValue('name');

        if ($packageName === null) {
            return null;
        }

        try {
            return InstalledVersions::getPrettyVersion($packageName);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Returns an array with the version of the package for AboutCommand.
     *
     * @return array<string|Closure>
     */
    private function version(): array
    {
        return [
            'Version' => fn () => $this->getVersion(),
        ];
    }

    /**
     * Returns additional data for AboutCommand.
     *
     * @return array<string, string|Closure>
     */
    public function aboutData(): array
    {
        return $this->aboutData;
    }

    /**
     * Sets the additional data to be displayed in the AboutCommand.
     *
     * @param  array<string, string|Closure>  $data  The additional about data.
     */
    public function setAboutData(array $data): static
    {
        $this->aboutData = $data;

        return $this;
    }

    /**
     * Merges version data and additional information for AboutCommand.
     *
     * @return array<string|Closure>
     */
    private function data(): array
    {
        if (! empty($this->getVersion())) {
            return array_merge($this->version(), $this->aboutData());
        }

        return $this->aboutData();
    }

    /**
     * Adds data to the AboutCommand.
     */
    public function bootAboutCommand(): void
    {
        if (! empty($this->name)) {
            AboutCommand::add(section: $this->name, data: $this->data());
        }
    }

    /**
     * Sets whether the package should include information in the AboutCommand.
     *
     * @param  bool  $value  Whether the package should be "aboutable."
     */
    public function hasAbout(bool $value = true): static
    {
        $this->isAboutable = $value;

        return $this;
    }

    /**
     * Sets the version of the package.
     *
     * @param  string  $version  The version of the package.
     */
    public function hasVersion(string $version): static
    {
        $this->version = $version;

        return $this;
    }

    /**
     * Whether the package is aboutable.
     */
    public function isAboutable(): bool
    {
        return $this->isAboutable;
    }
}
