<?php

namespace NyonCode\LaravelPackageToolkit\Concerns;

use Closure;

trait HasEvents
{
    /**
     * @var bool Whether the package registers events.
     */
    private bool $isEventable = false;

    /**
     * @var array<string, array<int, string|Closure>> Map of event => listeners.
     */
    protected array $events = [];

    /**
     * @var array<int, string> Event subscriber classes.
     */
    protected array $subscribers = [];

    /**
     * Determine if the package registers any events or subscribers.
     */
    public function isEventable(): bool
    {
        return $this->isEventable;
    }

    /**
     * Get the registered event => listeners map.
     *
     * @return array<string, array<int, string|Closure>>
     */
    public function events(): array
    {
        return $this->events;
    }

    /**
     * Get the registered event subscriber classes.
     *
     * @return array<int, string>
     */
    public function subscribers(): array
    {
        return $this->subscribers;
    }

    /**
     * Register multiple event listeners.
     *
     * Accepts a map of event class => listener(s). A listener may be a single
     * class-string/closure or an array of them.
     *
     * @param  array<string, string|Closure|array<int, string|Closure>>  $events
     */
    public function hasEvents(array $events): static
    {
        foreach ($events as $event => $listeners) {
            $this->events[$event] = array_merge(
                $this->events[$event] ?? [],
                is_array($listeners) ? $listeners : [$listeners]
            );
        }

        if (! empty($this->events)) {
            $this->isEventable = true;
        }

        return $this;
    }

    /**
     * Register a single event with one or more listeners.
     *
     * @param  string|Closure|array<int, string|Closure>  $listeners
     */
    public function hasEvent(string $event, string|Closure|array $listeners): static
    {
        return $this->hasEvents([$event => $listeners]);
    }

    /**
     * Register multiple event subscriber classes.
     *
     * @param  array<int, string>  $subscribers
     */
    public function hasSubscribers(array $subscribers): static
    {
        if (! empty($subscribers)) {
            $this->subscribers = array_merge(
                $this->subscribers,
                array_values($subscribers)
            );
        }

        if (! empty($this->subscribers)) {
            $this->isEventable = true;
        }

        return $this;
    }

    /**
     * Register a single event subscriber class.
     */
    public function hasSubscriber(string $subscriber): static
    {
        return $this->hasSubscribers([$subscriber]);
    }
}
