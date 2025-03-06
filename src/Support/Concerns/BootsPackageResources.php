<?php

namespace NyonCode\LaravelPackageToolkit\Support\Concerns;

use Seld\JsonLint\ParsingException;
use View;

trait BootsPackageResources
{
    use BladeComponentLoader;


    /**
     * Boot the package resources.
     *
     * This method boots the package by calling other methods that
     * register the package's resources. It calls the following methods
     * in order: `bootAboutCommand`, `bootMigrations`, `bootRoutes`,
     * `bootSharedViewData`, `bootTranslations`, `bootViewComposers`,
     * `bootViewComponentNamespaces`, `bootViewComponents`, and
     * `bootViews`.
     *
     * @throws ParsingException
     *
     * @return void
     */
    public function bootPackageResources(): void
    {
        $this->bootAboutCommand()
            ->bootMigrations()
            ->bootRoutes()
            ->bootSharedViewData()
            ->bootTranslations()
            ->bootVewComposers()
            ->bootViewComponentNamespaces()
            ->bootViewComponents()
            ->bootViews()
        ;
    }

    /**
     * Boot the about command for the package.
     *
     * This method checks if the package has an about command and, if so,
     * registers it by calling the `bootAboutCommand` method on the packager.
     *
     * @throws ParsingException
     *
     * @return static
     */
    public function bootAboutCommand(): static
    {
        if (! $this->packager->isAboutable()) {
            return $this;
        }

        $this->packager->bootAboutCommand();

        return $this;
    }

    /**
     * Boot the migrations for the package.
     *
     * This method loads the migration files for the package using the paths
     * provided by the packager. It ensures that the package is migratable
     * before attempting to load the migrations.
     *
     * @return static
     */
    public function bootMigrations(): static
    {
        if (! $this->packager->isMigratable() and $this->packager->hasMigrationsOnRun) {
            return $this;
        }

        foreach ($this->packager->migrationFiles() as $migrationFile) {
            $this->loadMigrationsFrom($migrationFile->getPathname());
        }

        return $this;
    }

    /**
     * Boot the routes for the package.
     *
     * This method loads the route files for the package using the paths
     * provided by the packager. It ensures that the package is routable
     * before attempting to load the routes.
     *
     * @return static
     */
    public function bootRoutes(): static
    {
        if (! $this->packager->isRoutable()) {
            return $this;
        }

        foreach ($this->packager->routeFiles() as $routeFile) {
            $this->loadRoutesFrom(path: $routeFile->getPathname());
        }

        return $this;
    }

    /**
     * Boot the shared view data for the package.
     *
     * This method checks if the package has shared data for views. If so, it iterates
     * through the shared data and registers each key-value pair with the View facade.
     * This allows the package to share data across all views.
     *
     * @return static
     */
    public function bootSharedViewData(): static
    {
        if (! $this->packager->isSharedWithViews()) {
            return $this;
        }
        foreach ($this->packager->viewSharedData() as $key => $value) {
            View::share($key, $value);
        }

        return $this;
    }

    /**
     * Boot the translations for the package.
     *
     * This method checks if the package is translatable and, if so, loads the
     * translations from the translation path provided by the packager. It
     * loads both PHP and JSON translations.
     *
     * @return static
     */
    public function bootTranslations(): static
    {
        if (! $this->packager->isTranslatable()) {
            return $this;
        }

        $this->loadTranslationsFrom(
            $this->packager->translationPath(),
            $this->packager->shortName()
        );

        $this->loadJsonTranslationsFrom(
            path: $this->packager->translationPath()
        );

        return $this;
    }

    /**
     * Boot the view composers for the package.
     *
     * This method iterates through the view composers registered in the package
     * and registers them with the View facade. This allows the package to
     * bind data to views when they are rendered.
     *
     * @return static
     */
    public function bootVewComposers(): static
    {
        if (! $this->packager->isViewComposable()) {
            return $this;
        }

        foreach ($this->packager->viewComposers() as $view => $callback) {
            View::composer($view, $callback);
        }

        return $this;
    }

    /**
     * Boot the view component namespaces for the package.
     *
     * This method checks if the view component namespaces are configured and,
     * if so, loads the namespaces using the packager's configuration.
     *
     * @return static
     */
    public function bootViewComponentNamespaces(): static
    {
        if (! $this->packager->isViewComponentNamespaceConfigured()) {
            return $this;
        }

        $this->loadViewComponentNamespaces($this->packager->viewComponentNamespaces());

        return $this;
    }

    /**
     * Boot the view components for the package.
     *
     * This method checks if the package is view composable and, if so,
     * loads the view components using the packager's configuration.
     *
     * @return static
     */
    public function bootViewComponents(): static
    {
        if (! $this->packager->isViewComposable()) {
            return $this;
        }
        $this->loadViewComponents($this->packager->viewComponents());

        return $this;
    }

    /**
     * Boot the views for the package.
     *
     * This method checks if the package has views and, if so, loads them
     * using the paths and namespace provided by the packager. This allows
     * the package views to be accessible using the package's short name as
     * the namespace.
     *
     * @return static
     */
    public function bootViews(): static
    {
        if (! $this->packager->isViewable()) {
            return $this;
        }

        $this->loadViewsFrom(
            path: $this->packager->views(),
            namespace: $this->packager->shortName()
        );

        return $this;
    }
}