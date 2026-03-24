<?php

namespace NyonCode\LaravelPackageToolkit\Commands;

use Closure;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use NyonCode\LaravelPackageToolkit\Commands\Concerns\PublishableResources;
use NyonCode\LaravelPackageToolkit\Packager;

class InstallCommand extends Command
{
    use PublishableResources;

    protected Packager $packager;

    /**
     * @var Closure[]
     */
    private array $beforeHooks = [];

    /**
     * @var Closure[]
     */
    private array $afterHooks = [];

    private bool $shouldShowProgress = true;

    /**
     * Create a new command instance.
     *
     * @param  Packager  $packager  The packager instance
     */
    public function __construct(Packager $packager)
    {
        $this->packager = $packager;

        // Odstraněn --no-interaction, protože je nativně přítomen v každém příkazu
        $this->signature = $this->packager->shortName().':install
                           {--force : Force the operation to run when in production}';

        $this->description = 'Install '.$this->packager->name.' package';
        $this->hidden = $packager->isInstallCommandHidden();

        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->showWelcomeMessage();

        // Execute before hooks
        $this->executeHooks($this->beforeHooks, 'Before Installation');

        // Main installation process
        $this->performInstallation();

        // Execute after hooks
        $this->executeHooks($this->afterHooks, 'After Installation');

        $this->showCompletionMessage();
    }

    /**
     * Add a hook to run before installation.
     *
     * @param  Closure  $callback  The callback to execute
     */
    public function beforeInstallation(Closure $callback): static
    {
        $this->beforeHooks[] = $callback;

        return $this;
    }

    /**
     * Add a hook to run after installation.
     *
     * @param  Closure  $callback  The callback to execute
     */
    public function afterInstallation(Closure $callback): static
    {
        $this->afterHooks[] = $callback;

        return $this;
    }

    /**
     * Disable progress indicators.
     */
    public function silent(): static
    {
        $this->shouldShowProgress = false;

        return $this;
    }

    /**
     * Execute installation hooks.
     *
     * @param  array  $hooks  The hooks to execute
     * @param  string  $phase  The phase of the installation
     */
    private function executeHooks(array $hooks, string $phase): void
    {
        if (empty($hooks)) {
            return;
        }

        if ($this->shouldShowProgress && $this->hasLaravelPrompts()) {
            $this->info("⚙️ $phase hooks...");
        }

        foreach ($hooks as $hook) {
            $hook($this);
        }
    }

    /**
     * Perform the main installation process.
     */
    private function performInstallation(): void
    {
        if (empty($this->installCommandTags)) {
            $this->warn('No installation steps configured.');

            return;
        }

        $steps = $this->getInstallationSteps();
        $currentStep = 0;
        $totalSteps = count($steps);

        foreach ($steps as $step => $tags) {
            $currentStep++;

            if ($this->shouldShowProgress && $this->hasLaravelPrompts()) {
                $this->showProgress($step, $currentStep, $totalSteps);
            }

            $this->publishTags($tags);
        }
    }

    /**
     * Get organized installation steps.
     */
    private function getInstallationSteps(): array
    {
        $stepOrder = [
            'Publishing configuration' => ['config'],
            'Publishing migrations' => ['migrations'],
            'Publishing assets' => ['assets'],
            'Publishing translations' => ['translations'],
            'Publishing views' => ['views'],
            'Publishing service providers' => ['providers'],
            'Publishing routes' => ['routes'],
        ];

        $steps = [];
        foreach ($stepOrder as $stepName => $stepTags) {
            $matchingTags = array_intersect($this->installCommandTags, $stepTags);
            if (! empty($matchingTags)) {
                $steps[$stepName] = $matchingTags;
            }
        }

        return $steps;
    }

    /**
     * Publish specific tags.
     *
     * @param  array  $tags  The tags to publish
     */
    private function publishTags(array $tags): void
    {
        foreach ($tags as $tag) {
            $publishTag = $this->packager->shortName()."::$tag";

            try {
                $exitCode = Artisan::call('vendor:publish', [
                    '--tag' => $publishTag,
                    '--force' => $this->option('force'),
                ]);

                if ($exitCode !== 0) {
                    $this->error("Failed to publish $tag");
                } elseif (! $this->option('no-interaction')) {
                    $this->line("  ✅ Published $tag");
                }
            } catch (Exception $e) {
                $this->error("Error publishing $tag: ".$e->getMessage());
            }
        }
    }

    /**
     * Show progress for current step.
     *
     * @param  string  $step  The step name
     * @param  int  $current  The current step number
     * @param  int  $total  The total number of steps
     */
    private function showProgress(string $step, int $current, int $total): void
    {
        if ($this->hasLaravelPrompts()) {
            // Laravel 10+ with Prompts
            $this->line("($current/$total) $step...");
        } else {
            // Laravel 9 fallback
            $percentage = round(($current / $total) * 100);
            $this->line("[$percentage%] $step...");
        }
    }

    /**
     * Show welcome message.
     */
    private function showWelcomeMessage(): void
    {
        if ($this->option('no-interaction')) {
            return;
        }

        $this->line('');
        $this->line("🚀 Installing <comment>{$this->packager->name}</comment>");
        $this->line('');

        // Environment check
        if (app()->environment('production') && ! $this->option('force')) {
            if (! $this->hasLaravelPrompts()) {
                $this->error('Cannot install in production without --force flag');
                exit(1);
            }

            $confirmed = $this->confirm(
                '⚠️  You are in production environment. Are you sure you want to continue?',
                false
            );

            if (! $confirmed) {
                $this->warn('Installation cancelled.');
                exit(0);
            }
        }
    }

    /**
     * Show completion message.
     */
    private function showCompletionMessage(): void
    {
        if ($this->option('no-interaction')) {
            return;
        }

        $this->line('');
        $this->info("✨ {$this->packager->name} installed successfully!");
        $this->line('');

        // Show next steps if available
        $nextSteps = $this->getNextSteps();
        if (! empty($nextSteps)) {
            $this->line('<comment>📋 Next steps:</comment>');
            foreach ($nextSteps as $step) {
                $this->line("  • $step");
            }
            $this->line('');
        }
    }

    /**
     * Get suggested next steps after installation.
     */
    private function getNextSteps(): array
    {
        $steps = [];

        if (in_array('config', $this->installCommandTags)) {
            $configFile = "config/{$this->packager->shortName()}.php";
            if (File::exists(config_path($this->packager->shortName().'.php'))) {
                $steps[] = "Review configuration in $configFile";
            }
        }

        if (in_array('migrations', $this->installCommandTags)) {
            $steps[] = 'Run: php artisan migrate';
        }

        return $steps;
    }

    /**
     * Copy and register service provider in app.php.
     *
     * @param  string|null  $providerClass  The service provider class to register.
     */
    public function copyAndRegisterServiceProviderInApp(?string $providerClass = null): static
    {
        return $this->beforeInstallation(function () use ($providerClass) {
            if ($providerClass) {
                $this->registerServiceProvider($providerClass);
            }
        });
    }

    /**
     * Register service provider in config/app.php.
     *
     * @param  string  $providerClass  The service provider class to register.
     *
     * @throws FileNotFoundException
     */
    private function registerServiceProvider(string $providerClass): void
    {
        $configPath = config_path('app.php');

        if (! File::exists($configPath)) {
            $this->warn('config/app.php not found. Skipping provider registration.');

            return;
        }

        $content = File::get($configPath);

        // Check if provider is already registered
        if (str_contains($content, $providerClass)) {
            $this->line('  ✅ Service provider already registered');

            return;
        }

        // Find providers array and add the new provider
        $pattern = "/(['\"]providers['\"]\s*=>\s*\[)(.*?)(\s*],)/s";

        if (preg_match($pattern, $content, $matches)) {
            $newProvider = "        $providerClass::class,\n";
            $replacement = $matches[1].$matches[2].$newProvider.$matches[3];
            $content = preg_replace($pattern, $replacement, $content);

            File::put($configPath, $content);
            $this->line('  ✅ Service provider registered in config/app.php');
        } else {
            $this->warn('Could not automatically register service provider');
        }
    }

    /**
     * Ask to star repository on GitHub.
     *
     * @param  string|null  $repoUrl  The URL of the repository on GitHub.
     * @return InstallCommand
     */
    public function askToStarRepoOnGitHub(?string $repoUrl = null): static
    {
        return $this->afterInstallation(function () use ($repoUrl) {
            if ($this->option('no-interaction')) {
                return;
            }

            if (! $repoUrl) {
                // Try to get from composer.json
                $repoUrl = $this->getRepositoryFromComposer();
            }

            if ($repoUrl && $this->hasLaravelPrompts()) {
                $this->line('');
                $shouldStar = $this->confirm(
                    '⭐ Would you like to star this package on GitHub?',
                    false
                );

                if ($shouldStar) {
                    if (PHP_OS_FAMILY === 'Darwin') {
                        exec("open '$repoUrl'");
                    } elseif (PHP_OS_FAMILY === 'Windows') {
                        exec("start '$repoUrl'");
                    } elseif (PHP_OS_FAMILY === 'Linux') {
                        exec("xdg-open '$repoUrl'");
                    }

                    $this->info('🌟 Thank you for your support!');
                } else {
                    $this->line("💝 Thank you for using {$this->packager->name}!");
                }
            }
        });
    }

    /**
     * Get repository URL from composer.json.
     */
    private function getRepositoryFromComposer(): ?string
    {
        try {
            $composerPath = $this->packager->path('/../composer.json');

            if (File::exists($composerPath)) {
                $composer = json_decode(File::get($composerPath), true);

                if (isset($composer['homepage'])) {
                    return $composer['homepage'];
                }

                if (isset($composer['support']['source'])) {
                    return $composer['support']['source'];
                }
            }
        } catch (Exception) {
            // Ignore errors
        }

        return null;
    }
}
