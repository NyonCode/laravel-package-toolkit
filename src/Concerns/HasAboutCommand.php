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
     * @var array<string, mixed>
     */
    private array $composerData = [];
    public string $version = '';
    private bool $isAboutable = false;

    /**
     * Retrieves a specific value from the composer.json file by key name.
     *
     * @param string $keyName The key to retrieve from composer.json.
     *
     * @throws ParsingException If the composer.json file cannot be parsed.
     *
     * @return string|null
     */
    private function getComposerValue(string $keyName): string|null
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
     *
     * @return string|null
     */
    public function getVersion(): string|null
    {
        if (!empty($this->version)) {
            return $this->version;
        }

        return InstalledVersions::getPrettyVersion(
            $this->getComposerValue('name')
        );
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
     * @throws ParsingException
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
     * @throws ParsingException
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
     *
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
     *
     * @return static
     */
    public function hasVersion(string $version): static
    {
        $this->version = $version;
        return $this;
    }


    /**
     * Whether the package is aboutable.
     *
     * @return bool
     */
    public function isAboutable(): bool
    {
        return $this->isAboutable;
    }
}
