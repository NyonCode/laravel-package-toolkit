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
     * @param  string  $providerPath  The name of the service provider.
     *
     * @throws FileNotFoundException
     */
    public function hasProvider(string $provider): static
    {
        $providerPath = $this->resolveFiles($provider);

        $this->providers = array_merge($this->providers, $providerPath);

        if (! empty($this->providers)) {
            $this->isProvidable = true;
        }

        return $this;
    }

    /**
     * Adds multiple service providers to the package.
     *
     * @param  array<string>  $providers
     * @return $this
     *
     * @throws FileNotFoundException
     */
    public function hasProviders(array $providers): static
    {
        if (! empty($providers)) {
            foreach ($providers as $provider) {
                $providerPath = $this->resolveFiles($provider);

                $this->providers = array_merge($this->providers, $providerPath);
            }
        }

        if (! empty($this->providers)) {
            $this->isProvidable = true;
        }

        return $this;
    }
}
