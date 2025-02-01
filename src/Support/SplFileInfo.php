<?php

namespace NyonCode\LaravelPackageToolkit\Support;

use SplFileInfo as BaseSplFileInfo;

class SplFileInfo extends BaseSplFileInfo

{
    private readonly string $baseFilename;

    public function __construct(string $filename)
    {
        parent::__construct($filename);
        $this->baseFilename = $this->getBasename('.' . $this->getExtension());
    }


    /**
     * Get the base filename (without extension).
     *
     * @return string The base filename
     */
    public function getBaseFilename(): string
    {
        return $this->baseFilename;
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