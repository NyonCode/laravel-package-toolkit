<?php

declare(strict_types=1);

namespace NyonCode\LaravelPackageToolkit;

use Composer\InstalledVersions;
use Exception;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\Compilers\BladeCompiler;
use NyonCode\LaravelPackageToolkit\Contracts\ProvidesPackageServices;
use NyonCode\LaravelPackageToolkit\Exceptions\InvalidReturnTypeException;
use NyonCode\LaravelPackageToolkit\Exceptions\MissingNameException;
use NyonCode\LaravelPackageToolkit\Support\SplFileInfo;
use ReflectionClass;
use Seld\JsonLint\ParsingException;

abstract class PackageServiceProvider extends ServiceProvider implements ProvidesPackageServices
{
    /**
     * The separator used for tagging resources.
     *
     * @var string
     */
    public string $tagSeparator = '::';

    /**
     * Whether the about command has been registered.
     *
     * @var bool
     */
    private static bool $isPackageAboutRegistered = false;

    /**
     * Instance of the Packager class.
     *
     * @var Packager
     */
    protected Packager $packager;

    /**
     * Configure the packager instance.
     *
     * @param Packager $packager
     *
     * @return void
     */
    abstract public function configure(Packager $packager): void;

    /**
     * Actions to perform before registering the package.
     *
     * @return void
     */
    public function registeringPackage(): void
    {
        // #Define any actions to be performed before registering the package.
    }


    /**
     * Register the package services.
     *
     * This method initializes the package by booting the packager, setting the base path,
     * and configuring it. It ensures that the package has a valid name and registers the
     * configuration files. It also calls custom actions before and after registering the
     * package.
     *
     * @throws MissingNameException if the package does not have a name.
     * @throws Exception
     *
     * @return void
     */
    public function register(): void
    {
        $this->registeringPackage();

        $this->packager = $this->bootPackager();
        $this->packager->hasBasePath($this->getPackageBaseDir());
        $this->configure($this->packager);

        $this->registerConfig();


        if (empty($this->packager->name)) {
            throw new MissingNameException('This package does not have a name. You can set one with `$package->name("")');
        }


        $this->registeredPackage();
    }

    /**
     * Actions to perform after registering the package.
     *
     * @return void
     */
    public function registeredPackage(): void
    {
        // #Define any actions to be performed after registering the package.
    }

    /**
     * Actions to perform before booting the package.
     *
     * @return void
     */
    public function bootingPackage(): void
    {
        // #Define any actions to be performed before booting the package.
    }

    /**
     * Boot the service provider.
     *
     * @throws ParsingException
     *
     * @return void
     */
    public function boot(): void
    {
        $this->bootingPackage();

        $this->registerPublishing();

        $this->registerPackageCommands();

        if (!self::$isPackageAboutRegistered) {
            AboutCommand::add(
                section: 'Laravel Package Toolkit',
                data: [
                    'Version' => fn() => InstalledVersions::getPrettyVersion(
                        'nyoncode/laravel-package-toolkit'
                    ),
                ]
            );

            self::$isPackageAboutRegistered = true;
        }

        if ($this->packager->isRoutable) {
            $this->loadRoutes();
        }

        if ($this->packager->isMigratable and $this->packager->hasMigrationsOnRun) {
            $this->loadMigrations();
        }

        if ($this->packager->isTranslatable) {
            $this->loadTranslations();

            if ($this->packager->loadJsonTranslate) {
                $this->loadJsonTranslations();
            }
        }

        if ($this->packager->isView) {
            $this->loadViews();
        }

        if ($this->packager->isViewComponents) {
            $this->loadViewComponents($this->packager->viewComponents());
        }

        if($this->packager->isViewComponentNamespaces) {
            $this->loadViewComponentNamespaces($this->packager->viewComponentNamespaces());
        }


        if ($this->packager->isAboutable()) {
            $this->bootAboutCommand();
        }

        $this->bootedPackage();

    }

    /**
     * Actions to perform after booting the package.
     *
     * @return void
     */
    public function bootedPackage(): void
    {
        // #Define any actions to be performed after booting the package.
    }

    /**
     * Create and return a new Packager instance.
     *
     * @return Packager
     */
    public function bootPackager(): Packager
    {
        return new Packager();
    }

    /**
     * Get about command
     *
     * @throws ParsingException
     *
     * @return void
     */
    public function bootAboutCommand(): void
    {
        $this->packager->bootAboutCommand();
    }

    /**
     * Get the base directory of the package.
     *
     * @return string
     */
    public function getPackageBaseDir(): string
    {
        $reflector = new ReflectionClass(get_class($this));
        return dirname($reflector->getFileName());
    }

    /**
     * Register the package configuration files.
     *
     * @throws Exception
     *
     * @return void
     */
    protected function registerConfig(): void
    {
        if (!empty($this->packager->configFiles())) {
            foreach ($this->packager->configFiles() as $configFile) {
                if (!is_array(require $configFile->getPathname())) {
                    throw new InvalidReturnTypeException(
                        'Configuration file [' . $configFile->getBaseFileName() . '] must return an array.'
                    );
                }

                $this->mergeConfigFrom(
                    path: $configFile->getPathname(),
                    key: $configFile->getBaseFileName()
                );
            }
        }
    }

    /**
     * Load the route files for the package.
     *
     * @return void
     */
    protected function loadRoutes(): void
    {
        foreach ($this->packager->routeFiles() as $routeFile) {
            $this->loadRoutesFrom(path: $routeFile->getPathname());
        }
    }

    /**
     * Load the migration files for the package.
     *
     * @return void
     */
    protected function loadMigrations(): void
    {
        foreach ($this->packager->migrationFiles() as $migrationFile) {
            $this->loadMigrationsFrom($migrationFile->getPathname());
        }
    }

    /**
     * Load the translation files for the package.
     *
     * @return void
     */
    protected function loadTranslations(): void
    {
        $this->loadTranslationsFrom(
            $this->packager->translationPath(),
            $this->packager->shortName()
        );
    }

    /**
     * Load the JSON translation files for the package.
     *
     * @return void
     */
    protected function loadJsonTranslations(): void
    {
        $this->loadJsonTranslationsFrom(
            path: $this->packager->translationPath()
        );
    }

    /**
     * Load the views for the package.
     *
     * This method loads the views for the package using the view path
     * provided by the packager and the short name of the package as the
     * namespace.
     *
     * @return void
     */
    protected function loadViews(): void
    {
        $this->loadViewsFrom(
            path: $this->packager->views(),
            namespace: $this->packager->shortName()
        );
    }

    /**
     * Load the view components registered in the package.
     *
     * This method uses the components registered in the packager and registers them
     * with Blade.
     *
     * @param array<mixed> $components
     *
     * @return void
     */
    protected function loadViewComponents(array $components): void
    {
        $this->callAfterResolving(BladeCompiler::class, function (BladeCompiler $blade) use ($components) {
            foreach ($components as $config) {
                $blade->component(
                    $config['component'],
                    $config['alias'] ?? null,
                    $config['prefix'] ?? null
                );
            }
        });
    }

    /**
     * Register the view component namespaces for the package.
     *
     * This method takes an associative array of namespace prefixes and namespaces
     * and registers them with Blade.
     *
     * @param array<string, string> $namespaces An associative array of namespace prefixes and namespaces.
     *
     * @return void
     */
    protected function loadViewComponentNamespaces(array $namespaces): void
    {
        $this->callAfterResolving(BladeCompiler::class, function ($blade) use ($namespaces) {

            foreach ($namespaces as $prefix => $namespace) {
                $blade->componentNamespace($namespace, $prefix);
            }
        });
    }

    /**
     * Publish the package configuration files.
     *
     * @return void
     */
    private function publishConfig(): void
    {
        foreach ($this->packager->configFiles() as $configFile) {
            $this->publishes(
                paths: [
                    $configFile->getPathname() => config_path(
                        $configFile->getBasename()
                    ),
                ],
                groups: "{$this->packager->shortName()}::config"
            );
        }
    }

    /**
     * Publish the migration files for the package.
     *
     * @return void
     */
    protected function publishMigrations(): void
    {
        /** @var SplFileInfo $migrationPath */
        $migrationPath = collect($this->packager->migrationFiles())->first();

        $this->publishes(
            paths: [
                $migrationPath->getPath() => database_path('migrations'),
            ],
            groups: $this->publishTagFormat('migrations')
        );
    }

    /**
     * Publish the translation files for the package.
     *
     * @return void
     */
    protected function publishTranslations(): void
    {
        $this->publishes(
            paths: [
                $this->packager->translationPath() => lang_path(
                    "vendor/{$this->packager->shortName()}"
                ),
            ],
            groups: $this->publishTagFormat('translations')
        );
    }

    /**
     * Publish the view files for the package.
     *
     * @return void
     */
    protected function publishViews(): void
    {
        $this->publishes(
            paths: [
                $this->packager->views() => resource_path(
                    "views/vendor/{$this->packager->shortName()}"
                ),
            ],
            groups: $this->publishTagFormat('views')
        );
    }

    /**
     * Get the tag separator for publishing.
     *
     * @return string
     */
    public function tagSeparator(): string
    {
        return $this->tagSeparator;
    }

    /**
     * Format the publishing tag for a given group.
     *
     * @param string $groupName
     *
     * @return string
     */
    public function publishTagFormat(string $groupName): string
    {
        return $this->packager->shortName() .
            $this->tagSeparator() .
            $groupName;
    }

    /**
     * Register the package's publishable resources.
     *
     * @return void
     */
    protected function registerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            if ($this->packager->isConfigurable) {
                $this->publishConfig();
            }

            if ($this->packager->isMigratable) {
                $this->publishMigrations();
            }

            if ($this->packager->isTranslatable) {
                $this->publishTranslations();
            }

            if ($this->packager->isView) {
                $this->publishViews();
            }
        }
    }

    /**
     * Register package-specific console commands.
     *
     * This method checks if the application is running in the console
     * and, if so, registers the package commands.
     *
     * @return void
     */
    public function registerPackageCommands(): void
    {
        if ($this->app->runningInConsole() and $this->packager->isCommandable) {
            $this->commands($this->packager->commands);
        }
    }

    /**
     * Get the list of package commands.
     *
     * Override this method to return an array of console commands
     * specific to the package.
     *
     * @return array<string|object> List of command classes.
     */
    public function packageCommands(): array
    {
        return [];
    }
}
