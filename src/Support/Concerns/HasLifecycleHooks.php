<?php

namespace NyonCode\LaravelPackageToolkit\Support\Concerns;

use Closure;
use NyonCode\LaravelPackageToolkit\Support\Enums\LifecycleHook;
use Throwable;

trait HasLifecycleHooks
{
    /**
     * @var bool Whether the booting callback has been defined
     */
    public bool $bootingDefined = false;

    /**
     * @var Closure|null The booting callback
     */
    private ?Closure $booting = null;

    /**
     * @var bool Whether the booted callback has been defined
     */
    public bool $bootedDefined = false;

    /**
     * @var Closure|null The booted callback
     */
    private ?Closure $booted = null;

    /**
     * @var bool Whether the registering callback has been defined
     */
    public bool $registeringDefined = false;

    /**
     * @var Closure|null The registering callback
     */
    private ?Closure $registering = null;

    /**
     * @var bool Whether the registered callback has been defined
     */
    public bool $registeredDefined = false;

    /**
     * @var Closure|null The registered callback
     */
    private ?Closure $registered = null;

    /**
     * Define a callback that will be called before the package has been booted.
     *
     * This callback is called during the `boot` method of the service provider.
     * It allows you to perform any setup that is necessary before the package is booted.
     *
     * @param  Closure  $callback  The callback to be called
     * @return $this
     */
    public function bootingPackage(Closure $callback): static
    {
        $this->booting = $callback;

        if (! $this->bootingDefined) {
            $this->bootingDefined = true;
        }

        return $this;
    }

    /**
     * Define a callback that will be called after the package has been booted.
     *
     * This callback is called during the `boot` method of the service provider.
     * It allows you to perform any setup that is necessary after the package is booted.
     *
     * @param  Closure  $callback  The callback to be called
     * @return $this
     */
    public function bootedPackage(Closure $callback): static
    {
        $this->booted = $callback;

        if (! $this->bootedDefined) {
            $this->bootedDefined = true;
        }

        return $this;
    }

    /**
     * Define a callback that will be called before the package has been registered.
     *
     * This callback is called during the `register` method of the service provider.
     * It allows you to perform any setup that is necessary before the package is registered.
     *
     * @param  Closure  $callback  The callback to be called
     * @return $this
     */
    public function registeringPackage(Closure $callback): static
    {
        $this->registering = $callback;

        if (! $this->registeringDefined) {
            $this->registeringDefined = true;
        }

        return $this;
    }

    /**
     * Define a callback that will be called after the package has been registered.
     *
     * This callback is called during the `register` method of the service provider.
     * It allows you to perform any setup that is necessary after the package is registered.
     *
     * @param  Closure  $callback  The callback to be called
     * @return $this
     */
    public function registeredPackage(Closure $callback): static
    {
        $this->registered = $callback;

        if (! $this->registeredDefined) {
            $this->registeredDefined = true;
        }

        return $this;
    }

    /**
     * Safely execute a lifecycle hook.
     *
     * @param  LifecycleHook  $hook The lifecycle hook to execute
     */
    public function executeLifecycleHook(LifecycleHook $hook): void
    {
        $hookName = $hook->value;

        $definedProperty = $hookName . 'Defined';
        $callbackProperty = $hookName;

        if (! $this->{$definedProperty}) {
            return;
        }

        $callback = $this->{$callbackProperty} ?? null;

        if ($callback instanceof Closure) {
            $callback($this);
        }
    }
}
