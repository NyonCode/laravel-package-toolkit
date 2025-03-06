<?php

namespace NyonCode\LaravelPackageToolkit\Support\Concerns;

use NyonCode\LaravelPackageToolkit\Support\SplFileInfo;

trait PublishesPackageResources
{
    /**
     * Register the package's publishable resources.
     *
     * @return void
     */
    public function registerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishAssets()
                ->publishConfig()
                ->publishMigrations()
                ->publishProvider()
                ->publishTranslations()
                ->publishViews();
        }
    }

    /**
     * Publish the package assets.
     *
     * The assets are published to the `public/vendor/<package-short-name>` directory.
     *
     * @return static
     */
    public function publishAssets(): static
    {
        if (! $this->packager->isAssetable()) {
            return $this;
        }

        $this->publishes(
            paths: [
                $this->packager->assetDirectory() => public_path(
                    path: 'vendor/' . $this->packager->shortName()
                ),
            ],
            groups: $this->publishTagFormat('assets')
        );

        return $this;
    }

    /**
     * Publish the package configuration files.
     *
     * @return static
     */
    public function publishConfig(): static
    {
        if (! $this->packager->isConfigurable()) {
            return $this;
        }

        $publishesConfig = [];

        foreach ($this->packager->configFiles() as $configFile) {
            $publishesConfig[$configFile->getPathname()] = config_path($configFile->getBasename());
        }

        $this->publishes(
            paths: $publishesConfig,
            groups: $this->publishTagFormat('config')
        );

        return $this;
    }

    /**
     * Publish the migration files for the package.
     *
     * @return static
     */
    public function publishMigrations(): static
    {
        if (! $this->packager->isMigratable()) {
            return $this;
        }

        /** @var SplFileInfo $migrationPath */
        $migrationPath = collect($this->packager->migrationFiles())->first();

        $this->publishes(
            paths: [
                $migrationPath->getPath() => database_path('migrations'),
            ],
            groups: $this->publishTagFormat('migrations')
        );

        return $this;
    }

    /**
     * Publish the service providers for the package.
     *
     * The service providers are published to the `app/Providers/vendor/<package-short-name>` directory.
     *
     * @return static
     */
    public function publishProvider(): static
    {
        if (! $this->packager->isProvidable()) {
            return $this;
        }
        $providers = [];

        foreach ($this->packager->providers() as $provider) {
            $providers[$provider->getPathname()] = app_path('Providers/' . $provider->getBaseFilename() . '.php');
        }

        $this->publishes(
            paths: $providers,
            groups: $this->publishTagFormat('providers')
        );

        return $this;
    }


    /**
     * Publish the translation files for the package.
     *
     * @return static
     */
    public function publishTranslations(): static
    {
        if (! $this->packager->isTranslatable()) {
            return $this;
        }

        if (function_exists(lang_path())) {
            $this->publishes(
                paths: [
                    $this->packager->translationPath() => lang_path(
                        "vendor/{$this->packager->shortName()}"
                    ),
                ],
                groups: $this->publishTagFormat('translations')
            );
        } else {
            $this->publishes(
                paths: [
                    $this->packager->translationPath() => resource_path(
                        "lang/vendor/{$this->packager->shortName()}"
                    ),
                ],
                groups: $this->publishTagFormat('translations')
            );
        }

        return $this;
    }

    /**
     * Publish the view component namespaces registered in the package.
     *
     * This method maps the paths of the view component namespaces to the destination path
     * where they will be published. The destination path is determined by the
     * package's short name and the last directory name of the view component namespace path.
     *
     * For example, if the package's short name is "example" and the view component
     * namespace path is "resources/views/components/example", the destination path will be
     * "app/View/Components/example".
     *
     * If the `$publishPaths` array is not empty, the view components are published
     * using the `publishes` method, with the `view-components` group.
     *
     * @return static
     */
    protected function publishViewComponentNamespaces(): static
    {
        if (! $this->packager->isViewComponentNamespaceConfigured()) {
            return $this;
        }

        $shortName = $this->packager->shortName();

        $publishComponentPaths = collect(
            $this->packager->viewComponentNamespaces()
        )
            ->mapWithKeys(function ($namespace) use ($shortName) {
                $sourcePath = $this->getPathFromNamespace($namespace);

                if (!$sourcePath || !is_dir($sourcePath)) {
                    return [];
                }

                $directoryName = basename($sourcePath);
                $destinationPath = base_path(
                    "/app/View/Components/$shortName/$directoryName"
                );

                return [$sourcePath => $destinationPath];
            })
            ->all();

        if (!empty($publishComponentPaths)) {
            $this->publishes(
                paths: $publishComponentPaths,
                groups: $this->publishTagFormat('view-component-namespaces')
            );
        }

        return $this;
    }

    /**
     * Publish the view components registered in the package.
     *
     * This method maps the paths of the view components to the destination path
     * where they will be published. The destination path is determined by the
     * package's short name and the last directory name of the view component path.
     *
     * For example, if the package's short name is "example" and the view component
     * path is "resources/views/components/example", the destination path will be
     * "app/View/Components/example".
     *
     * If the `$publishPaths` array is not empty, the view components are published
     * using the `publishes` method, with the `view-components` group.
     *
     * @return static
     */
    protected function publishViewComponents(): static
    {
        if (! $this->packager->isViewComponentized()) {
            return $this;
        }

        $shortName = $this->packager->shortName();

        $publishComponentPaths = collect($this->packager->viewComponentPaths())
            ->mapWithKeys(function ($sourcePath) use ($shortName) {
                $directoryName = basename($sourcePath);
                $destinationPath = base_path(
                    "app/View/Components/$shortName/$directoryName"
                );

                return [$sourcePath => $destinationPath];
            })
            ->all();

        if (!empty($publishComponentPaths)) {
            $this->publishes(
                paths: $publishComponentPaths,
                groups: $this->publishTagFormat('view-components')
            );
        }

        return $this;
    }

    /**
     * Publish the view files for the package.
     *
     * @return static
     */
    public function publishViews(): static
    {
        if (! $this->packager->isViewable()) {
            return $this;
        }

        $this->publishes(
            paths: [
                $this->packager->views() => resource_path(
                    "views/vendor/{$this->packager->shortName()}"
                ),
            ],
            groups: $this->publishTagFormat('views')
        );

        return $this;
    }
}