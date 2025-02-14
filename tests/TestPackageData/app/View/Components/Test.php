<?php

namespace NyonCode\LaravelPackageToolkit\Tests\TestPackageData\app\View\Components;

use Illuminate\View\Component;

class Test extends Component
{

    public function __construct(public  string $name) {}

    public function render()
    {
        return view('test-package::test.test');
    }
}
