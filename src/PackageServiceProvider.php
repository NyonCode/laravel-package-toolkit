<?php

declare(strict_types=1);

namespace NyonCode\LaravelPackageToolkit;

use Composer\InstalledVersions;
use Exception;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Support\ServiceProvider;
use NyonCode\LaravelPackageToolkit\Contracts\ProvidesPackageServices;
use NyonCode\LaravelPackageToolkit\Exceptions\InvalidReturnTypeException;
use NyonCode\LaravelPackageToolkit\Exceptions\MissingNameException;
use NyonCode\LaravelPackageToolkit\Support\Concerns\BootsPackageResources;
use NyonCode\LaravelPackageToolkit\Support\Concerns\HasEnvironmentChecks;
use NyonCode\LaravelPackageToolkit\Support\Concerns\HasNamespaceResolver;
use NyonCode\LaravelPackageToolkit\Support\Concerns\HasPublishingTag;
use NyonCode\LaravelPackageToolkit\Support\Concerns\PublishesPackageResources;
use ReflectionClass;
use Seld\JsonLint\ParsingException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

abstract class PackageServiceProvider extends ServiceProvider implements ProvidesPackageServices
{
    use BootsPackageResources;
    use HasEnvironmentChecks;
    use HasNamespaceResolver;
    use HasPublishingTag;
    use PublishesPackageResources;

    /**
     * Whether the about command has been registered.
     */
    private static bool $isPackageAboutRegistered = false;

    /**
     * Instance of the Packager class.
     */
    protected Packager $packager;

    /**
     * Configure the packager instance.
     */
    abstract public function configure(Packager $packager): void;

    /**
     * Actions to perform before registering the package.
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
     */
    public function register(): void
    {
        $this->registeringPackage();

        $this->packager = $this->bootPackager();
        $this->packager->hasBasePath($this->getPackageBaseDir());
        $this->configure($this->packager);

        $this->registerConfig();

        if (empty($this->packager->name)) {
            throw new MissingNameException(
                'This package does not have a name. You can set one with `$package->name("")'
            );
        }

        // Register install command if enabled
        $this->registerInstallCommand();

        // Auto-install if configured
        $this->performAutoInstall();

        $this->registeredPackage();
    }

    /**
     * Actions to perform after registering the package.
     */
    public function registeredPackage(): void
    {
        // #Define any actions to be performed after registering the package.
    }

    /**
     * Actions to perform before booting the package.
     */
    public function bootingPackage(): void
    {
        // #Define any actions to be performed before booting the package.
    }

    /**
     * Boot the service provider.
     *
     * @throws ParsingException
     */
    public function boot(): void
    {
        $this->bootingPackage();
        $this->registerPublishing();
        $this->registerPackageCommands();

        if (! self::$isPackageAboutRegistered) {
            AboutCommand::add(
                section: 'Laravel Package Toolkit',
                data: [
                    'Version' => fn () => InstalledVersions::getPrettyVersion(
                        'nyoncode/laravel-package-toolkit'
                    ),
                ]
            );

            self::$isPackageAboutRegistered = true;
        }

        $this->bootPackageResources();

        $this->bootedPackage();
    }

    /**
     * Actions to perform after booting the package.
     */
    public function bootedPackage(): void
    {
        // #Define any actions to be performed after booting the package.
    }

    /**
     * Create and return a new Packager instance.
     */
    public function bootPackager(): Packager
    {
        return new Packager();
    }

    /**
     * Register the package configuration files.
     *
     * @throws Exception
     */
    protected function registerConfig(): void
    {
        if (! empty($this->packager->configFiles())) {
            foreach ($this->packager->configFiles() as $configFile) {
                if (! is_array(require $configFile->getPathname())) {
                    throw new InvalidReturnTypeException(
                        'Configuration file ['.
                        $configFile->getBaseFileName().
                        '] must return an array.'
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
     * Register the install command if enabled.
     */
    protected function registerInstallCommand(): void
    {
        if (! $this->packager->isInstallable()) {
            return;
        }

        if ($this->app->runningInConsole()) {
            $installCommand = $this->packager->createInstallCommand();
            $this->commands([$installCommand]);
        }
    }

    /**
     * Perform auto-installation if configured.
     */
    protected function performAutoInstall(): void
    {
        if (! $this->packager->shouldInstallOnRun()) {
            return;
        }

        // Schedule auto-installation to run after all providers are booted
        $this->app->booted(function () {
            if ($this->app->runningInConsole()) {
                $this->performSilentInstallation();
            }
        });
    }

    /**
     * Perform silent installation without user interaction.
     *
     * @throws Exception
     */
    protected function performSilentInstallation(): void
    {
        try {
            $installCommand = $this->packager->createInstallCommand();

            // Create a mock input/output for silent execution
            $input = new ArrayInput([
                '--no-interaction' => true,
            ]);

            $output = new NullOutput();

            $installCommand->run($input, $output);
        } catch (Exception $e) {
            // Log the error but don't break the application
            if ($this->app->hasDebugModeEnabled()) {
                throw $e;
            }
        }
    }

    /**
     * Get the base directory of the package.
     */
    public function getPackageBaseDir(): string
    {
        $reflector = new ReflectionClass(get_class($this));

        return dirname($reflector->getFileName());
    }

    /**
     * Register package-specific console commands.
     *
     * This method checks if the application is running in the console
     * and, if so, registers the package commands.
     */
    public function registerPackageCommands(): void
    {
        if (
            $this->app->runningInConsole() and $this->packager->isCommandable()
        ) {
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
