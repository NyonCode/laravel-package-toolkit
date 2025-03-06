<?php

namespace NyonCode\LaravelPackageToolkit\Support\Concerns;

trait HasPublishingTag
{
    /**
     * The separator used for tagging resources.
     *
     * @var string
     */
    public string $tagSeparator = '::';

    /**
     * Get the tag separator for publishing.
     *
     * @return string
     */
    public function tagSeparator(): string
    {
        return $this->tagSeparator;
    }

    /**
     * Format the publishing tag for a given group.
     *
     * @param string $groupName
     *
     * @return string
     */
    public function publishTagFormat(string $groupName): string
    {
        return $this->packager->shortName() .
            $this->tagSeparator() .
            $groupName;
    }

}