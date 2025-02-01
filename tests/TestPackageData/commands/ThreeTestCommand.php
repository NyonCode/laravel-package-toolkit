<?php

namespace NyonCode\LaravelPackageToolkit\Tests\TestPackageData\commands;

use Illuminate\Console\Command;

class ThreeTestCommand extends Command
{
    protected $signature = 'app:three-test';


    public function handle(): void
    {
        $this->info('Successful test');
    }
}
