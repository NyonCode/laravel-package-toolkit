<?php

declare(strict_types=1);

namespace NyonCode\LaravelPackageToolkit\Support\Concerns;

trait PublishesPackageResources
{
    /**
     * Register the package's publishable resources.
     */
    public function registerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishAssets()
                ->publishConfig()
                ->publishFactories()
                ->publishMigrations()
                ->publishProvider()
                ->publishRoutes()
                ->publishSeeders()
                ->publishStubs()
                ->publishTranslations()
                ->publishViewComponentNamespaces()
                ->publishViewComponents()
                ->publishViews();
        }
    }

    /**
     * Publish the package assets.
     *
     * The assets are published to the `public/vendor/<package-short-name>` directory,
     * under the package's own `assets` tag *and* Laravel's conventional
     * `laravel-assets` tag.
     *
     * The second tag earns its place because it is what the Laravel application
     * skeleton runs from composer's `post-update-cmd`
     * (`vendor:publish --tag=laravel-assets --ansi --force`) — the same hook Horizon,
     * Telescope and Nova rely on. One command covers every installed package, which
     * the per-package tag cannot express. Laravel accumulates groups per path, so both
     * tags publish the same files and an untagged `vendor:publish` is unaffected.
     */
    public function publishAssets(): static
    {
        if (! $this->packager->isAssetable()) {
            return $this;
        }

        $groups = (array) $this->publishTagFormat('assets');
        $groups[] = 'laravel-assets';

        $this->publishes(
            paths: [
                $this->packager->assetDirectory() => public_path(
                    path: 'vendor/'.$this->packager->shortName()
                ),
            ],
            groups: $groups
        );

        return $this;
    }

    /**
     * Publish the package configuration files.
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
     * Supports both timestamped and timeless migrations. Timeless migrations
     * automatically receive a timestamp prefix when published to ensure
     * correct execution order.
     */
    public function publishMigrations(): static
    {
        if (! $this->packager->isMigratable()) {
            return $this;
        }

        $this->publishes(
            paths: $this->packager->getMigrationPublishMapping(),
            groups: $this->publishTagFormat('migrations')
        );

        return $this;
    }

    /**
     * Publish the route files for the package.
     *
     * The route files are published to the `routes/vendor/<package-short-name>` directory.
     */
    public function publishRoutes(): static
    {
        if (! $this->packager->isRoutable()) {
            return $this;
        }

        $routes = [];

        foreach ($this->packager->routeFiles() as $routeFile) {
            $routes[$routeFile->getPathname()] = base_path(
                "routes/vendor/{$this->packager->shortName()}/{$routeFile->getBasename()}"
            );
        }

        $this->publishes(
            paths: $routes,
            groups: $this->publishTagFormat('routes')
        );

        return $this;
    }

    /**
     * Publish the service providers for the package.
     *
     * The service providers are published to the `app/Providers/vendor/<package-short-name>` directory.
     */
    public function publishProvider(): static
    {
        if (! $this->packager->isProvidable()) {
            return $this;
        }
        $providers = [];

        foreach ($this->packager->providers() as $provider) {
            $providers[$provider->getPathname()] = app_path('Providers/'.$provider->getBaseFilename().'.php');
        }

        $this->publishes(
            paths: $providers,
            groups: $this->publishTagFormat('providers')
        );

        return $this;
    }

    /**
     * Publish the seeder files for the package.
     *
     * Published flat into `database/seeders`, not into a `vendor/<short-name>`
     * subdirectory: that is where the application's own `Database\Seeders` namespace
     * resolves, so `db:seed --class=Database\Seeders\<Name>` works on a published file
     * without the consumer rewriting a sub-namespace first.
     *
     * A `.stub` source is published as `.php`, the same convention `publishProvider()`
     * follows, so seeders can ship as inert stubs.
     */
    public function publishSeeders(): static
    {
        if (! $this->packager->isSeedable()) {
            return $this;
        }

        $seeders = [];

        foreach ($this->packager->seederFiles() as $seederFile) {
            $seeders[$seederFile->getPathname()] = database_path(
                'seeders/'.$seederFile->getBaseFileName().'.php'
            );
        }

        $this->publishes(
            paths: $seeders,
            groups: $this->publishTagFormat('seeders')
        );

        return $this;
    }

    /**
     * Publish the factory files for the package.
     *
     * Published flat into `database/factories` for the same reason seeders are: the
     * application's `Database\Factories` namespace resolves that directory.
     */
    public function publishFactories(): static
    {
        if (! $this->packager->isFactorable()) {
            return $this;
        }

        $factories = [];

        foreach ($this->packager->factoryFiles() as $factoryFile) {
            $factories[$factoryFile->getPathname()] = database_path(
                'factories/'.$factoryFile->getBaseFileName().'.php'
            );
        }

        $this->publishes(
            paths: $factories,
            groups: $this->publishTagFormat('factories')
        );

        return $this;
    }

    /**
     * Publish the stub files for the package.
     *
     * Published to `stubs/<package-short-name>`, keeping the original extension. The
     * subdirectory matters because `stubs/` is a single flat directory shared with
     * `php artisan stub:publish` and with every other package.
     */
    public function publishStubs(): static
    {
        if (! $this->packager->isStubbable()) {
            return $this;
        }

        $stubs = [];

        foreach ($this->packager->stubFiles() as $stubFile) {
            $stubs[$stubFile->getPathname()] = base_path(
                "stubs/{$this->packager->shortName()}/{$stubFile->getBasename()}"
            );
        }

        $this->publishes(
            paths: $stubs,
            groups: $this->publishTagFormat('stubs')
        );

        return $this;
    }

    /**
     * Publish the translation files for the package.
     */
    public function publishTranslations(): static
    {
        if (! $this->packager->isTranslatable()) {
            return $this;
        }

        if (function_exists('lang_path')) {
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
     */
    public function publishViewComponentNamespaces(): static
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

                if (! $sourcePath || ! is_dir($sourcePath)) {
                    return [];
                }

                $directoryName = basename($sourcePath);
                $destinationPath = base_path(
                    "/app/View/Components/$shortName/$directoryName"
                );

                return [$sourcePath => $destinationPath];
            })
            ->all();

        if (! empty($publishComponentPaths)) {
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
     */
    public function publishViewComponents(): static
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

        if (! empty($publishComponentPaths)) {
            $this->publishes(
                paths: $publishComponentPaths,
                groups: $this->publishTagFormat('view-components')
            );
        }

        return $this;
    }

    /**
     * Publish the view files for the package.
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
