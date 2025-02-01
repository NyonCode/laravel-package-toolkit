<?php

namespace NyonCode\LaravelPackageToolkit\Concerns;

trait HasOptimize
{
    public bool $isOptimizable = false;

    public function hasOptimize(bool $value = true): static
    {

    }
}