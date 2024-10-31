<?php

namespace NyonCode\LaravelPackageBuilder\Support;

class SplFileInfo extends \SplFileInfo
{
    private readonly string $baseFilename;

    public function __construct(string $filename)
    {
        parent::__construct($filename);
        $this->baseFilename = $this->getBasename('.' . $this->getExtension());
    }

    /**
     * @return string
     */
    public function getBaseFilename(): string
    {
        return $this->baseFilename;
    }
}