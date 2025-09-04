<?php

namespace NyonCode\LaravelPackageToolkit\Tests\TestPackageData\src\Commands;

use Illuminate\Console\Command;

class TestCommand extends Command
{
    protected $signature = 'app:test';

    public function handle(): void
    {
        $this->info('Successful');
    }
}
