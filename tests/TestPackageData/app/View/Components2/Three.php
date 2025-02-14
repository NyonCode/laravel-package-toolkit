<?php

namespace NyonCode\LaravelPackageToolkit\Tests\TestPackageData\app\View\Components2;

use Illuminate\View\Component;

class Three extends Component
{
    public function __construct(public string $name) {}

    public function render(): string
    {
        return <<<'blade'
            {{ $name }}
        blade;
    }
}
