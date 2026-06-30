<?php

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
use NyonCode\LaravelPackageToolkit\Support\Enums\LifecycleHook;
use ReflectionClass;
use Seld\JsonLint\ParsingException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Throwable;

abstract class PackageServiceProvider extends ServiceProvider implements ProvidesPackageServices
{
    use BootsPackageResources;
    use HasEnvironmentChecks;
    use HasNamespaceResolver;
    use HasPublishingTag;
    use PublishesPackageResources;

    /**
     * Whether the about command has been registered globally.
     */
    private static bool $isPackageAboutRegistered = false;

    /**
     * Instance of the Packager class.
     */
    protected ?Packager $packager = null;

    /**
     * Configure the packager instance.
     *
     * @param  Packager  $packager  The packager instance to configure
     *
     * @throws PackageConfigurationException When configuration fails
     */
    abstract public function configure(Packager $packager): void;

    /**
     * Actions to perform before registering the package.
     *
     * Safely executes the registering lifecycle hook if defined.
     *
     * @throws Throwable
     */
    public function registeringPackage(): void
    {
        $this->packager->executeLifecycleHook(LifecycleHook::Registering);
    }

    /**
     * Actions to perform after registering the package.
     *
     * Safely executes the registered lifecycle hook if defined.
     *
     * @throws Throwable
     */
    public function registeredPackage(): void
    {
        $this->packager->executeLifecycleHook(LifecycleHook::Registered);
    }

    /**
     * Actions to perform before booting the package.
     *
     * Safely executes the booting lifecycle hook if defined.
     */
    public function bootingPackage(): void
    {
        $this->packager->executeLifecycleHook(LifecycleHook::Booting);
    }

    /**
     * Actions to perform after booting the package.
     *
     * Safely executes the booted lifecycle hook if defined.
     */
    public function bootedPackage(): void
    {
        $this->packager->executeLifecycleHook(LifecycleHook::Booted);
    }

    /**
     * Register the package services.
     *
     * This method initializes the package by booting the packager, setting the base path,
     * and configuring it. It ensures that the package has a valid name and registers the
     * configuration files. It also calls custom actions before and after registering the
     * package.
     *
     * @throws MissingNameException When the package does not have a name
     * @throws PackageConfigurationException When package configuration fails
     * @throws Exception|Throwable When registration fails
     */
    public function register(): void
    {
        $this->packager = $this->bootPackager();
        $this->validatePackager();
        $this->packager->hasBasePath($this->getPackageBaseDir());

        // Configure package
        $this->configure($this->packager);

        // Execute conditional callbacks immediately after configuration
        $this->packager->executeConditionalCallbacks();

        $this->validatePackageConfiguration();

        // Execute registering package hooks
        $this->registeringPackage();

        $this->registerConfig();
        $this->registerInstallCommand();
        $this->performAutoInstall();

        // Execute registered package hooks
        $this->registeredPackage();
    }

    /**
     * Boot the service provider.
     *
     * @throws ParsingException When parsing fails
     * @throws Exception When booting fails
     */
    public function boot(): void
    {
        $this->bootingPackage();

        $this->registerPublishing();
        $this->registerPackageCommands();
        $this->registerAboutCommand();
        $this->bootPackageResources();

        $this->bootedPackage();
    }

    /**
     * Create and return a new Packager instance.
     *
     * @return Packager The packager instance
     */
    public function bootPackager(): Packager
    {
        return new Packager();
    }

    /**
     * Get the base directory of the package.
     *
     * @return string The package base directory path
     *
     * @throws PackageConfigurationException
     */
    public function getPackageBaseDir(): string
    {
        $reflector = new ReflectionClass(static::class);
        $filename = $reflector->getFileName();

        if ($filename === false) {
            throw new PackageConfigurationException(
                'Unable to determine package base directory from reflection'
            );
        }

        return dirname($filename);
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
            ! $this->app->runningInConsole() ||
            ! $this->packager?->isCommandable()
        ) {
            return;
        }

        $commands = $this->packager->commands ?? [];

        if (! empty($commands)) {
            $this->commands($commands);
        }
    }

    /**
     * Get the list of package commands.
     *
     * Override this method to return an array of console commands
     * specific to the package.
     *
     * @return array<string|object> List of command classes
     */
    public function packageCommands(): array
    {
        return [];
    }

    /**
     * Get the additional data for the AboutCommand.
     *
     * Override this method to add custom key-value pairs to your package's
     * section in the `php artisan about` command output.
     *
     * @return array<string, string|\Closure> Custom about data
     */
    public function aboutData(): array
    {
        return [];
    }

    /**
     * Validate the packager instance.
     *
     * @throws PackageConfigurationException When packager is invalid
     */
    protected function validatePackager(): void
    {
        if ($this->packager === null) {
            throw new PackageConfigurationException(
                'Packager instance is null'
            );
        }
    }

    /**
     * Validate package configuration after setup.
     *
     * @throws MissingNameException When package name is missing
     */
    protected function validatePackageConfiguration(): void
    {
        if (empty($this->packager?->name)) {
            throw new MissingNameException(
                'This package does not have a name. You can set one with $package->name("package-name")'
            );
        }
    }

    /**
     * Register the package configuration files.
     *
     * @throws InvalidReturnTypeException When config file doesn't return array
     * @throws Exception When registration fails
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
     * Register the installation command if enabled.
     */
    protected function registerInstallCommand(): void
    {
        if (
            ! $this->packager?->isInstallable() ||
            ! $this->app->runningInConsole()
        ) {
            return;
        }

        $installCommand = $this->packager->createInstallCommand();
        $this->commands([$installCommand]);
    }

    /**
     * Perform auto-installation if configured.
     */
    protected function performAutoInstall(): void
    {
        if (! $this->packager?->shouldInstallOnRun()) {
            return;
        }

        $this->app->booted(function () {
            if ($this->app->runningInConsole()) {
                $this->performSilentInstallation();
            }
        });
    }

    /**
     * Perform silent installation without user interaction.
     */
    protected function performSilentInstallation(): void
    {
        $installCommand = $this->packager?->createInstallCommand();

        if ($installCommand === null) {
            return;
        }

        $input = new ArrayInput(['--no-interaction' => true]);
        $output = new NullOutput();

        $installCommand->run($input, $output);
    }

    /**
     * Register the about command information.
     */
    protected function registerAboutCommand(): void
    {
        if (self::$isPackageAboutRegistered) {
            return;
        }

        AboutCommand::add(
            section: 'Laravel Package Toolkit',
            data: [
                'Version' => fn () => $this->getToolkitVersion(),
            ]
        );

        self::$isPackageAboutRegistered = true;
    }

    /**
     * Get the toolkit version.
     *
     * @return string The toolkit version
     */
    protected function getToolkitVersion(): string
    {
        try {
            return InstalledVersions::getPrettyVersion(
                'nyoncode/laravel-package-toolkit'
            ) ?? 'unknown';
        } catch (Throwable) {
            return 'unknown';
        }
    }
}
