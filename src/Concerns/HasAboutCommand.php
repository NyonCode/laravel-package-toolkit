<?php

namespace NyonCode\LaravelPackageToolkit\Concerns;

use Closure;
use Composer\InstalledVersions;
use Composer\Json\JsonFile;
use Illuminate\Foundation\Console\AboutCommand;
use Seld\JsonLint\ParsingException;

trait HasAboutCommand
{
    /**
     * @var array<string>
     */
    private array $composerData = [];
    public string $version = '';
    private bool $isAboutable = false;


    /**
     * Retrieves a specific value from the composer.json file by key name.
     *
     * @param string $keyName The key to retrieve from composer.json.
     * @return string The value associated with the given key, or an empty string if not found.
     * @throws ParsingException
     */
    private function getComposerValue(string $keyName): string
    {
        $jsonFile = new JsonFile($this->path('/../composer.json'));
        if ($jsonFile->exists()) {
            $this->composerData = $jsonFile->read();
        }

        return $this->composerData[$keyName] ?? '';
    }

    /**
     * Retrieves the version of the package.
     *
     * @return string The version of the package.
     * @throws ParsingException
     */
    public function getVersion(): string
    {
        return empty($this->version)
            ? InstalledVersions::getPrettyVersion(
                $this->getComposerValue('name')
            )
            : $this->version;
    }

    /**
     * Returns an array with the version of the package for AboutCommand.
     *
     * @return array<string|Closure>
     */
    private function version(): array
    {
        return [
            'Version' => fn() => $this->getVersion(),
        ];
    }

    /**
     * Returns additional data for AboutCommand.
     *
     * @return array<string|Closure>
     */
    public function aboutData(): array
    {
        return [];
    }

    /**
     * Merges version data and additional information for AboutCommand.
     *
     * @return array<string|Closure>
     */
    private function data(): array
    {
        if (!empty($this->getVersion())) {
            return array_merge($this->version(), $this->aboutData());
        }

        return $this->aboutData();
    }

    /**
     * Adds data to the AboutCommand.
     *
     * @return void
     */
    public function bootAboutCommand(): void
    {
        if (!empty($this->name)) {
            AboutCommand::add(section: $this->name, data: $this->data());
        }
    }

    /**
     * Sets whether the package should include information in the AboutCommand.
     *
     * @param bool $value Whether the package should be "aboutable."
     * @return static
     */
    public function hasAbout(bool $value = true): static
    {
        $this->isAboutable = $value;
        return $this;
    }

    /**
     * Sets the version of the package.
     *
     * @param string $version The version of the package.
     * @return static
     */
    public function hasVersion(string $version): static
    {
        $this->version = $version;
        return $this;
    }

    /**
     * @return bool
     */
    public function isAboutable(): bool
    {
        return $this->isAboutable;
    }
}
