<?php

namespace NyonCode\LaravelPackageToolkit\Concerns;

use Closure;
use Throwable;

trait HasConditionalLoading
{
    /**
     * @var array<Closure> Conditional callbacks to be executed
     */
    private array $conditionalCallbacks = [];

    /**
     * @var bool Flag to prevent double execution of callbacks
     */
    private bool $conditionalCallbacksExecuted = false;

    /**
     * Conditionally configure the package based on a condition.
     *
     * @param  bool  $condition  The condition to check
     * @param  Closure  $callback  The callback to execute if condition is true
     */
    public function when(bool $condition, Closure $callback): static
    {
        if ($condition) {
            $this->conditionalCallbacks[] = $callback;
        }

        return $this;
    }

    /**
     * Conditionally configure the package when condition is false.
     *
     * @param  bool  $condition  The condition to check
     * @param  Closure  $callback  The callback to execute if condition is false
     */
    public function unless(bool $condition, Closure $callback): static
    {
        return $this->when(! $condition, $callback);
    }

    /**
     * Execute all conditional callbacks.
     */
    public function executeConditionalCallbacks(): static
    {
        if ($this->conditionalCallbacksExecuted) {
            return $this; // Prevent double execution
        }

        foreach ($this->conditionalCallbacks as $callback) {
            try {
                $callback($this);
            } catch (Throwable $e) {
                // Log error but continue with other callbacks
                error_log('Error in conditional callback: '.$e->getMessage());
            }
        }

        $this->conditionalCallbacksExecuted = true;

        return $this;
    }

    /**
     * Add multiple conditional configurations at once.
     *
     * @param  array<array{condition: bool, callback: Closure}>  $conditions
     */
    public function whenMultiple(array $conditions): static
    {
        foreach ($conditions as $config) {
            if (isset($config['condition']) && isset($config['callback'])) {
                $this->when($config['condition'], $config['callback']);
            }
        }

        return $this;
    }

    /**
     * Conditionally execute callback based on environment.
     *
     * @param  string|array  $environments  Environment(s) to check
     * @param  Closure  $callback  The callback to execute
     */
    public function whenEnvironment(string|array $environments, Closure $callback): static
    {
        $environments = is_array($environments) ? $environments : [$environments];
        $currentEnv = $this->getCurrentEnvironment();

        return $this->when(
            in_array($currentEnv, $environments, true),
            $callback
        );
    }

    /**
     * Conditionally execute callback in production environment.
     *
     * @param  Closure  $callback  The callback to execute
     */
    public function whenProduction(Closure $callback): static
    {
        return $this->whenEnvironment('production', $callback);
    }

    /**
     * Conditionally execute callback in local environment.
     *
     * @param  Closure  $callback  The callback to execute
     */
    public function whenLocal(Closure $callback): static
    {
        return $this->whenEnvironment(['local', 'development'], $callback);
    }

    /**
     * Conditionally execute callback when running in console.
     *
     * @param  Closure  $callback  The callback to execute
     */
    public function whenConsole(Closure $callback): static
    {
        return $this->when(
            php_sapi_name() === 'cli' || (function_exists('app') && app()->runningInConsole()),
            $callback
        );
    }

    /**
     * Conditionally execute callback when a class exists.
     *
     * @param  string  $class  The class to check for
     * @param  Closure  $callback  The callback to execute
     */
    public function whenClassExists(string $class, Closure $callback): static
    {
        return $this->when(class_exists($class), $callback);
    }

    /**
     * Conditionally execute callback when a function exists.
     *
     * @param  string  $function  The function to check for
     * @param  Closure  $callback  The callback to execute
     */
    public function whenFunctionExists(string $function, Closure $callback): static
    {
        return $this->when(function_exists($function), $callback);
    }

    /**
     * Conditionally execute callback when an extension is loaded.
     *
     * @param  string  $extension  The extension to check for
     * @param  Closure  $callback  The callback to execute
     */
    public function whenExtensionLoaded(string $extension, Closure $callback): static
    {
        return $this->when(extension_loaded($extension), $callback);
    }

    /**
     * Reset conditional callbacks (useful for testing).
     */
    public function resetConditionalCallbacks(): static
    {
        $this->conditionalCallbacks = [];
        $this->conditionalCallbacksExecuted = false;

        return $this;
    }

    /**
     * Check if conditional callbacks have been executed.
     */
    public function conditionalCallbacksExecuted(): bool
    {
        return $this->conditionalCallbacksExecuted;
    }

    /**
     * Get count of pending conditional callbacks.
     */
    public function getPendingConditionalCallbacksCount(): int
    {
        return $this->conditionalCallbacksExecuted ? 0 : count($this->conditionalCallbacks);
    }

    /**
     * Get the current environment.
     *
     * This method can be overridden in classes using this trait
     * to provide custom environment detection logic.
     *
     * Following Laravel standard behavior:
     * - Use app()->environment() if available (recommended)
     * - Fallback to config('app.env') if config is available
     * - Fallback to direct ENV variables as last resort
     * - Default to 'production' for security
     */
    protected function getCurrentEnvironment(): string
    {
        // Try Laravel app environment first
        if (function_exists('app')) {
            try {
                return app()->environment();
            } catch (\Throwable $e) {
                // Continue to fallback
            }
        }

        // Try config helper (works when config is cached)
        if (function_exists('config')) {
            try {
                $environment = config('app.env');
                if (!empty($environment) && is_string($environment)) {
                    return $environment;
                }
            } catch (\Throwable $e) {
                // Continue to fallback
            }
        }

        // Fallback to environment variables
        $env = $_ENV['APP_ENV'] ?? $_ENV['ENVIRONMENT'] ?? getenv('APP_ENV') ?: getenv('ENVIRONMENT');

        if (!empty($env) && is_string($env)) {
            return strtolower(trim($env));
        }

        // Secure default
        return 'production';
    }
}
