<?php

namespace NyonCode\LaravelPackageToolkit\Concerns;

trait HasOptimize
{
    /**
     * @var bool Whether the package registers optimize commands.
     */
    private bool $isOptimizable = false;

    /**
     * @var array<int, array{optimize: string|null, clear: string|null, key: string|null}>
     */
    protected array $optimizeCommands = [];

    /**
     * Determine if the package registers any optimize commands.
     */
    public function isOptimizable(): bool
    {
        return $this->isOptimizable;
    }

    /**
     * Get the registered optimize command definitions.
     *
     * @return array<int, array{optimize: string|null, clear: string|null, key: string|null}>
     */
    public function optimizeCommands(): array
    {
        return $this->optimizeCommands;
    }

    /**
     * Register artisan commands to run during `php artisan optimize`
     * and `php artisan optimize:clear`.
     *
     * Mirrors Laravel's ServiceProvider::optimizes(). At least one of
     * `$optimize` or `$clear` must be provided, otherwise the call is a no-op.
     * When registering more than one entry, pass a distinct `$key` for each —
     * entries sharing a key overwrite one another (Laravel keys by provider).
     *
     * @param  string|null  $optimize  Command name to run on `optimize`
     * @param  string|null  $clear  Command name to run on `optimize:clear`
     * @param  string|null  $key  Cache key (defaults to the package short name at boot)
     */
    public function hasOptimizeCommands(
        ?string $optimize = null,
        ?string $clear = null,
        ?string $key = null
    ): static {
        if ($optimize === null && $clear === null) {
            return $this;
        }

        $this->optimizeCommands[] = [
            'optimize' => $optimize,
            'clear' => $clear,
            'key' => $key,
        ];

        $this->isOptimizable = true;

        return $this;
    }
}
