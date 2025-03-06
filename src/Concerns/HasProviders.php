<?php

namespace NyonCode\LaravelPackageToolkit\Concerns;

use Illuminate\Contracts\Filesystem\FileNotFoundException;

trait HasProviders
{
    use FilesResolver;

    private bool $isProvidable = false;
    protected array $providers = [];

    /**
     * Checks if the package is "providable".
     *
     * @return bool
     */
    public function isProvidable(): bool
    {
        return $this->isProvidable;
    }

    public function providers(): array
    {
        return $this->providers;
    }

    /**
     * Adds a service provider to the package.
     *
     * @param string $providerPath The name of the service provider.
     *
     * @throws FileNotFoundException
     *
     * @return static
     */
    public function hasProvider(string $providerPath): static
    {
        $providerPath = $this->resolveFiles($providerPath);

        $this->providers = array_merge($this->providers, $providerPath);

        if(! empty($this->providers)) {
            $this->isProvidable = true;
        }

        return $this;
    }
}