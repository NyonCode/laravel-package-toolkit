<?php

namespace NyonCode\LaravelPackageToolkit\Tests\TestPackageData\src\Commands;

use Illuminate\Console\Command;

class FiveTestCommand extends Command
{
    protected $signature = 'app:five-test';

    public function handle(): void
    {
        $this->info('Successful test');
    }
}
