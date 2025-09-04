<?php

namespace NyonCode\LaravelPackageToolkit\Tests\TestPackageData\src\Commands;

use Illuminate\Console\Command;

class SecondTestCommand extends Command
{
    protected $signature = 'app:second-test';

    public function handle(): void
    {
        $this->info('Successful test');
    }
}
