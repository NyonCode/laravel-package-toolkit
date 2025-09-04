<?php

namespace NyonCode\LaravelPackageToolkit\Commands\Concerns;

trait PublishableResources
{
    protected array $installCommandTags = [];

    /**
     * Add tags to the installation command.
     *
     * @param  string  ...$tag  The tags to add
     */
    private function publish(string ...$tag): static
    {
        $this->installCommandTags = array_merge($this->installCommandTags, $tag);

        return $this;
    }

    /**
     * Publish configuration files.
     */
    public function publishConfig(): static
    {
        return $this->publish('config');
    }

    /**
     * Alias for publishConfig for better readability.
     */
    public function publishConfigFile(): static
    {
        return $this->publishConfig();
    }

    /**
     * Publish configuration files.
     */
    public function publishConfigFiles(): static
    {
        return $this->publishConfig();
    }

    /**
     * Publish database migrations.
     */
    public function publishMigrations(): static
    {
        return $this->publish('migrations');
    }

    /**
     * Publish route files.
     */
    public function publishRoutes(): static
    {
        return $this->publish('routes');
    }

    /**
     * Publish route files.
     */
    public function publishRouteFiles(): static
    {
        return $this->publishRoutes();
    }

    /**
     * Publish translation files.
     */
    public function publishTranslations(): static
    {
        return $this->publish('translations');
    }

    /**
     * Publish translation files.
     */
    public function publishTranslationFiles(): static
    {
        return $this->publishTranslations();
    }

    /**
     * Publish language files (alias for translations).
     */
    public function publishLanguageFiles(): static
    {
        return $this->publishTranslations();
    }

    /**
     * Publish public assets.
     */
    public function publishAssets(): static
    {
        return $this->publish('assets');
    }

    /**
     * Publish public assets.
     */
    public function publishPublicAssets(): static
    {
        return $this->publishAssets();
    }

    /**
     * Publish view files.
     */
    public function publishViews(): static
    {
        return $this->publish('views');
    }

    /**
     * Publish view files.
     */
    public function publishViewFiles(): static
    {
        return $this->publishViews();
    }

    /**
     * Publish service provider files.
     */
    public function publishProviders(): static
    {
        return $this->publish('providers');
    }

    /**
     * Publish service provider files.
     */
    public function publishServiceProviders(): static
    {
        return $this->publishProviders();
    }

    /**
     * Publish view components.
     */
    public function publishComponents(): static
    {
        return $this->publish('view-components');
    }

    /**
     * Publish view components.
     */
    public function publishViewComponents(): static
    {
        return $this->publishComponents();
    }

    /**
     * Publish view component namespaces.
     */
    public function publishComponentNamespaces(): static
    {
        return $this->publish('view-component-namespaces');
    }

    /**
     * Publish view component namespaces.
     */
    public function publishViewComponentNamespaces(): static
    {
        return $this->publishComponentNamespaces();
    }

    /**
     * Publish all available resources.
     */
    public function publishEverything(): static
    {
        return $this->publish(
            'config',
            'migrations',
            'routes',
            'translations',
            'assets',
            'views',
            'providers',
            'view-components',
            'view-component-namespaces'
        );
    }

    /**
     * Publish all available resources (alias).
     */
    public function publishAll(): static
    {
        return $this->publishEverything();
    }

    /**
     * Publish essential files (config, migrations, assets).
     */
    public function publishEssentials(): static
    {
        return $this->publish('config', 'migrations', 'assets');
    }

    /**
     * Get all tags that will be published.
     */
    public function getPublishTags(): array
    {
        return $this->installCommandTags;
    }

    /**
     * Clear all publish tags.
     */
    public function clearPublishTags(): static
    {
        $this->installCommandTags = [];

        return $this;
    }

    /**
     * Check if specific tag will be published.
     *
     * @param  string  $tag  The tag to check
     */
    public function willPublish(string $tag): bool
    {
        return in_array($tag, $this->installCommandTags, true);
    }

    /**
     * Conditionally publish based on a condition.
     *
     * @param  bool  $condition  The condition to check
     */
    public function publishIf(bool $condition, string ...$tags): static
    {
        if ($condition) {
            return $this->publish(...$tags);
        }

        return $this;
    }

    /**
     * Publish unless condition is true.
     *
     * @param  bool  $condition  The condition to check
     * @param  string  ...$tags  The tags to publish
     */
    public function publishUnless(bool $condition, string ...$tags): static
    {
        return $this->publishIf(! $condition, ...$tags);
    }

    /**
     * Publish for specific environments.
     *
     * @param  string|array  $environments  The environments to publish
     * @param  string  ...$tags  The tags to publish
     */
    public function publishForEnvironment(string|array $environments, string ...$tags): static
    {
        $environments = is_array($environments) ? $environments : [$environments];
        $currentEnv = app()->environment();

        if (in_array($currentEnv, $environments, true)) {
            return $this->publish(...$tags);
        }

        return $this;
    }

    /**
     * Publish for production environment.
     *
     * @param  string  ...$tags  The tags to publish
     */
    public function publishForProduction(string ...$tags): static
    {
        return $this->publishForEnvironment('production', ...$tags);
    }

    /**
     * Publish for local development.
     *
     * @param  string  ...$tags  The tags to publish
     */
    public function publishForLocal(string ...$tags): static
    {
        return $this->publishForEnvironment('local', ...$tags);
    }

    /**
     * Publish custom tags.
     *
     * @param  string  ...$tags  The tags to publish
     */
    public function publishCustom(string ...$tags): static
    {
        return $this->publish(...$tags);
    }
}
