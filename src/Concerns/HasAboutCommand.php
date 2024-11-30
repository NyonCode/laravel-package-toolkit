<?php

namespace NyonCode\LaravelPackageBuilder\Concerns;

use Closure;
use Composer\InstalledVersions;
use Illuminate\Foundation\Console\AboutCommand;

trait HasAboutCommand
{
    protected array $composerRootPackageData = [];

    public function __construct()
    {
        $this->composerRootPackageData = InstalledVersions::getRootPackage();
    }

    /**
     * Get the version of the package.
     *
     * @return string
     */
    abstract public function isVersion(): string;

    /**
     * Returns an array with the version of the package for AboutCommand.
     *
     * @return array<string|Closure>
     */
    private function version(): array
    {
        return [
            'Version' => fn() => !empty($this->isVersion())
                ? $this->isVersion()
                : $this->composerRootPackageData['pretty_version'],
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
        if (!empty($this->isVersion())) {
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
        if (!empty($this->packager)) {
            AboutCommand::add(
                section: $this->packager->name,
                data: $this->data()
            );
        }
    }
}
