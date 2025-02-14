<?php

namespace NyonCode\LaravelPackageToolkit\Tests\TestPackageData\app\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Test extends Component
{

    public function __construct(public  string $name) {}

    public function render(): View
    {
        return view('test-package::test.test');
    }
}
