<?php

namespace NyonCode\LaravelPackageToolkit\Tests\TestPackageData\app\Listeners;

use NyonCode\LaravelPackageToolkit\Tests\TestPackageData\app\Events\OrderPlaced;

class RecordOrder
{
    /**
     * @var array<int, string> Ids handled during the test run.
     */
    public static array $handled = [];

    public function handle(OrderPlaced $event): void
    {
        self::$handled[] = $event->id;
    }
}
