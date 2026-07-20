<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\Tests\TestPackageData\app\Events\OrderPlaced;
use NyonCode\LaravelPackageToolkit\Tests\TestPackageData\app\Listeners\OrderSubscriber;
use NyonCode\LaravelPackageToolkit\Tests\TestPackageData\app\Listeners\RecordOrder;

trait PackageEventsClosureTest
{
    /**
     * Configure the packager instance
     */
    public function configure(Packager $packager): void
    {
        $packager
            ->name('Test Package')
            ->hasEvent(OrderPlaced::class, [
                RecordOrder::class,
                function (OrderPlaced $event) {
                    RecordOrder::$handled[] = 'closure:'.$event->id;
                },
            ])
            ->hasSubscribers([OrderSubscriber::class]);
    }
}

uses(PackageEventsClosureTest::class);

beforeEach(function () {
    RecordOrder::$handled = [];
    OrderSubscriber::$handled = [];
});

test('boot wires both class and closure listeners passed as an array', function () {
    event(new OrderPlaced('z1'));

    expect(RecordOrder::$handled)->toBe(['z1', 'closure:z1']);
});

test('boot registers subscribers passed via hasSubscribers', function () {
    event(new OrderPlaced('z2'));

    expect(OrderSubscriber::$handled)->toBe(['z2']);
});
