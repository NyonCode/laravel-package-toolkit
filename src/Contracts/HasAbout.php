<?php

namespace NyonCode\LaravelPackageToolkit\Contracts;

interface HasAbout
{
    /**
     * @return array<string, string>
     */
    public function aboutData(): array;

}