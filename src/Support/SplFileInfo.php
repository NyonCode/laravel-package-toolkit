<?php

namespace NyonCode\LaravelPackageToolkit\Support;

use SplFileInfo as BaseSplFileInfo;

class SplFileInfo extends BaseSplFileInfo

{
    private readonly string $baseFileName;

    public function __construct(string $filename)
    {
        parent::__construct($filename);
        $this->baseFileName = $this->getBasename('.' . $this->getExtension());
    }


    /**
     * Get the base filename (without extension).
     *
     * @return string The base filename
     */
    public function getBaseFileName(): string
    {
        return $this->baseFileName;
    }



    /**
     * Returns the size of the file in bytes.
     *
     * @return int The file size in bytes.
     */
    public function getFileSize(): int
    {
        return $this->getSize();
    }
}