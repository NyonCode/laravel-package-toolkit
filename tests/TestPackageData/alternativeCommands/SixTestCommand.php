<?php

namespace NyonCode\LaravelPackageToolkit\Tests\TestPackageData\alternativeCommands;

use Illuminate\Console\Command;

class SixTestCommand extends Command
{
    protected $signature = 'app:six-test';

    public function handle(): void
    {
        $this->info('Successful test');
    }
}
