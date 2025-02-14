<?php

namespace NyonCode\LaravelPackageToolkit\Tests\TestPackageData\app\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class Test extends Component
{

    public function __construct(public  string $name) {}

    public function render(): View
    {
        return view('test-package::test.test');
    }
}
