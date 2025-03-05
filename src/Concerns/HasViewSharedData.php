<?php

namespace NyonCode\LaravelPackageToolkit\Concerns;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use function Pest\Laravel\instance;
use function PHPUnit\Framework\isInstanceOf;

trait HasViewSharedData
{
    private bool $isSharedWithViews = false;

    private array $viewSharedData = [];

    /**
     * Determine if the view shared data is set.
     *
     * This method checks the flag indicating if there is any
     * shared data for the views.
     *
     * @return bool True if the shared data is set, false otherwise.
     */
    public function isSharedWithViews(): bool
    {
        return $this->isSharedWithViews;
    }

    /**
     * Get the shared data for the views.
     *
     * This method returns the array of shared data for the views.
     *
     * @return array The shared data for the views.
     */
    public function viewSharedData(): array
    {
        return $this->viewSharedData;
    }

    /**
     * Set the shared data for the views.
     *
     * This method sets the shared data for the views and
     * sets the `isSharedWithViews` flag to true if the
     * array is not empty.
     *
     * @param array $viewSharedData The shared data for the views
     *
     * @return static
     */
    public function hasSharedDataForAllViews(array $viewSharedData): static
    {
        foreach ($viewSharedData as $key => $value) {
            if (!is_string($key)) {
                throw new InvalidArgumentException(
                    message: "The shared data key [$key] must be a string."
                );
            }

            if (
                !is_scalar($value) and
                !is_array($value) and
                !is_null($value) and
                !($value instanceof Arrayable)
            ) {
                throw new InvalidArgumentException(
                    message: "The shared data value [$key] must be a scalar, array, null, or an instance of Arrayable."
                );
            }
        }

        $this->viewSharedData = array_merge(
            $this->viewSharedData,
            $viewSharedData
        );

        if (!empty($this->viewSharedData)) {
            $this->isSharedWithViews = true;
        }

        return $this;
    }
}
