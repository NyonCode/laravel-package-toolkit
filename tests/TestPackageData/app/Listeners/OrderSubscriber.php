<?php

namespace NyonCode\LaravelPackageToolkit\Tests\TestPackageData\app\Listeners;

use Illuminate\Contracts\Events\Dispatcher;
use NyonCode\LaravelPackageToolkit\Tests\TestPackageData\app\Events\OrderPlaced;

class OrderSubscriber
{
    /**
     * @var array<int, string> Ids handled during the test run.
     */
    public static array $handled = [];

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            OrderPlaced::class => 'onOrderPlaced',
        ];
    }

    public function onOrderPlaced(OrderPlaced $event): void
    {
        self::$handled[] = $event->id;
    }
}
