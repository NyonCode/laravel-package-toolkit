<?php

namespace NyonCode\LaravelPackageToolkit\Tests\TestPackageData\src\Commands;

use Illuminate\Console\Command;

class FourTestCommand extends Command
{
    protected $signature = 'app:four-test';

    public function handle(): void
    {
        $this->info('Successful test');
    }
}
