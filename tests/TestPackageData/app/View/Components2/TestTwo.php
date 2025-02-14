<?php

namespace NyonCode\LaravelPackageToolkit\Tests\TestPackageData\app\View\Components2;

use Illuminate\View\Component;

class TestTwo extends Component
{
    public function render(): string
    {
        return <<<'blade'
            {{ $slot }}
        blade;
    }
}