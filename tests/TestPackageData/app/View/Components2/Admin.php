<?php

namespace NyonCode\LaravelPackageToolkit\Tests\TestPackageData\app\View\Components2;

use Illuminate\View\Component;
use Illuminate\View\View;

class Admin extends Component
{


    public function __construct(
        public string $name
    ) {}

    public function render(): View
    {
        return view('test-package::test.admin');
    }
}
