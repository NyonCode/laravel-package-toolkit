<?php

namespace NyonCode\LaravelPackageToolkit\Concerns;

trait HasPublishTagSeparator
{
    /**
     * @var array<int, string> Custom publish tag separators. Empty falls back to the provider default (`::`).
     */
    protected array $publishTagSeparators = [];

    /**
     * Set the separator(s) used when building publish tags.
     *
     * Publish tags are formatted as `{shortName}{separator}{group}`. The default
     * separator is `::` (e.g. `backup-manager::config`). Pass `-` for the classic
     * flat format (e.g. `backup-manager-config`):
     *
     * ```php
     * $packager->hasPublishTagSeparator('-'); // vendor:publish --tag="backup-manager-config"
     * ```
     *
     * Pass an array to register every group under **multiple** tags at once, so
     * consumers can publish with either form:
     *
     * ```php
     * $packager->hasPublishTagSeparator(['::', '-']);
     * // both --tag="backup-manager::config" and --tag="backup-manager-config" work
     * ```
     *
     * The first separator is treated as primary (used by the install command).
     *
     * @param  string|array<int, string>  $separator  One separator or a list of separators
     */
    public function hasPublishTagSeparator(string|array $separator): static
    {
        $separators = is_array($separator) ? $separator : [$separator];

        $this->publishTagSeparators = array_values(array_unique($separators));

        return $this;
    }

    /**
     * Get the primary publish tag separator, or null when using the default.
     */
    public function publishTagSeparator(): ?string
    {
        return $this->publishTagSeparators[0] ?? null;
    }

    /**
     * Get all configured publish tag separators (empty when using the default).
     *
     * @return array<int, string>
     */
    public function publishTagSeparators(): array
    {
        return $this->publishTagSeparators;
    }
}
