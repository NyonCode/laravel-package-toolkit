<?php

namespace NyonCode\LaravelPackageToolkit\Tests\TestPackageData\app\View\Components2;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Admin extends Component
{


    public function __construct(
        public string $name
    ) {}

    public function render(): View
    {
        return $this->view('test-package::test.admin');
    }
}
