<?php

namespace NyonCode\LaravelPackageToolkit\Concerns;

use Closure;
use NyonCode\LaravelPackageToolkit\Commands\InstallCommand;

trait HasInstallation
{
    /**
     * @var bool Whether the package has an install command
     */
    protected bool $isInstallable = false;

    /**
     * @var bool Whether to install automatically on package boot
     */
    protected bool $installOnRun = false;

    /**
     * @var bool Whether to hide the install command from artisan list
     */
    protected bool $isInstallCommandHidden = false;

    /**
     * @var Closure|null The install command configuration callback
     */
    protected ?Closure $installCommandCallback = null;

    /**
     * @var string|null Custom install command name
     */
    protected ?string $customInstallCommandName = null;

    /**
     * Enable the install command for this package.
     *
     * @param  Closure|null  $callback  The install command configuration callback
     */
    public function hasInstallCommand(?Closure $callback = null): static
    {
        $this->isInstallable = true;
        $this->installCommandCallback = $callback;

        return $this;
    }

    /**
     * Set custom name for the install command.
     *
     * @param  string  $name  Command name (without package prefix)
     */
    public function installCommandName(string $name): static
    {
        $this->customInstallCommandName = $name;

        return $this;
    }

    /**
     * Hide the install command from artisan command list.
     *
     * @param  bool  $hidden  Whether to hide
     */
    public function installCommandHidden(bool $hidden = true): static
    {
        $this->isInstallCommandHidden = $hidden;

        return $this;
    }

    /**
     * Set whether to install automatically when package is registered.
     *
     * @param  bool  $installOnRun  Whether to auto-install
     */
    public function installOnRun(bool $installOnRun = true): static
    {
        $this->installOnRun = $installOnRun;

        return $this;
    }

    /**
     * Install only in specific environments.
     *
     * @param  string|array  $environments  Environment(s) to install
     */
    public function installOnRunInEnvironment(string|array $environments): static
    {
        $environments = is_array($environments) ? $environments : [$environments];

        if (in_array(app()->environment(), $environments, true)) {
            $this->installOnRun = true;
        }

        return $this;
    }

    /**
     * Install automatically in local environment.
     */
    public function installOnRunInLocal(): static
    {
        return $this->installOnRunInEnvironment('local');
    }

    /**
     * Install automatically in production environment.
     */
    public function installOnRunInProduction(): static
    {
        return $this->installOnRunInEnvironment('production');
    }

    /**
     * Check if the package has an install command.
     */
    public function isInstallable(): bool
    {
        return $this->isInstallable;
    }

    /**
     * Check if the install command should be hidden.
     */
    public function isInstallCommandHidden(): bool
    {
        return $this->isInstallCommandHidden;
    }

    /**
     * Check if the package should install automatically.
     */
    public function shouldInstallOnRun(): bool
    {
        return $this->installOnRun;
    }

    /**
     * Get the install command configuration callback.
     */
    public function getInstallCommandCallback(): ?Closure
    {
        return $this->installCommandCallback;
    }

    /**
     * Get the install command name.
     */
    public function getInstallCommandName(): string
    {
        if ($this->customInstallCommandName) {
            return $this->shortName().':'.$this->customInstallCommandName;
        }

        return $this->shortName().':install';
    }

    /**
     * Create and configure the install command instance.
     */
    public function createInstallCommand(): InstallCommand
    {
        $command = new InstallCommand($this);

        // Apply custom command name if set
        if ($this->customInstallCommandName) {
            $command->setName($this->getInstallCommandName());
        }

        // Configure the command if callback is provided
        if ($this->installCommandCallback) {
            ($this->installCommandCallback)($command);
        }

        return $command;
    }

    /**
     * Quick setup for common installation scenarios.
     */
    public function hasQuickInstall(): static
    {
        return $this->hasInstallCommand(function (InstallCommand $command) {
            $command->publishConfig()
                ->publishMigrations()
                ->publishAssets();
        });
    }

    /**
     * Setup full installation with all resources.
     */
    public function hasFullInstall(): static
    {
        return $this->hasInstallCommand(function (InstallCommand $command) {
            $command->publishEverything();
        });
    }

    /**
     * Setup minimal installation (config only).
     */
    public function hasMinimalInstall(): static
    {
        return $this->hasInstallCommand(function (InstallCommand $command) {
            $command->publishConfig();
        });
    }

    /**
     * Setup development installation with extra features.
     */
    public function hasDevInstall(): static
    {
        return $this->hasInstallCommand(function (InstallCommand $command) {
            $command->publishConfig()
                ->publishMigrations()
                ->publishViews()
                ->publishAssets()
                ->publishForLocal('routes'); // Only publish routes in local
        });
    }

    /**
     * Disable the installation command.
     */
    public function withoutInstallCommand(): static
    {
        $this->isInstallable = false;
        $this->installCommandCallback = null;

        return $this;
    }
}
