<?php

namespace NyonCode\LaravelPackageToolkit\Support\Concerns;

trait HasPublishingTag
{
    /**
     * @var string The default separator used for tagging resources.
     */
    public string $tagSeparator = '::';

    /**
     * Get the separators used for publishing tags.
     *
     * Separators configured on the packager (via `hasPublishTagSeparator()`)
     * take precedence over the provider default (`::`).
     *
     * @return array<int, string>
     */
    public function tagSeparators(): array
    {
        $configured = $this->packager?->publishTagSeparators() ?? [];

        return ! empty($configured) ? $configured : [$this->tagSeparator];
    }

    /**
     * Get the primary tag separator for publishing.
     *
     * The primary separator is the first configured one, used by the install
     * command when building the tag it publishes.
     */
    public function tagSeparator(): string
    {
        return $this->tagSeparators()[0];
    }

    /**
     * Format the publishing tag(s) for a given group.
     *
     * Returns a single tag when one separator is configured, or an array of
     * tags (one per separator) so the group is publishable under each form.
     *
     * @return string|array<int, string>
     */
    public function publishTagFormat(string $groupName): string|array
    {
        $shortName = $this->packager->shortName();

        $tags = array_map(
            fn (string $separator): string => $shortName.$separator.$groupName,
            $this->tagSeparators()
        );

        return count($tags) === 1 ? $tags[0] : $tags;
    }
}
