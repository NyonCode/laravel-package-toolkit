<?php

namespace NyonCode\LaravelPackageToolkit\Concerns;

use Closure;
use Composer\InstalledVersions;
use Composer\Json\JsonFile;
use Illuminate\Foundation\Console\AboutCommand;
use Seld\JsonLint\ParsingException;
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
     * @param  string  $keyName  The key to retrieve from composer.json.
     *
     * @throws ParsingException If the composer.json file cannot be parsed.
     */
    private function getComposerValue(string $keyName): ?string
    {
        $jsonFile = new JsonFile($this->path('/../composer.json'));
        if ($jsonFile->exists()) {
            $data = $jsonFile->read();
            $this->composerData = is_array($data) ? $data : [];
        }

        $value = $this->composerData[$keyName] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * Retrieves the version of the package.
     *
     * @throws ParsingException If the composer.json file cannot be parsed.
     */
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
     *
     * @throws ParsingException
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
     *
     * @throws ParsingException
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
