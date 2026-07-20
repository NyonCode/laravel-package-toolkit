<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\Tests\TestPackageData\app\Events\OrderPlaced;
use NyonCode\LaravelPackageToolkit\Tests\TestPackageData\app\Listeners\OrderSubscriber;
use NyonCode\LaravelPackageToolkit\Tests\TestPackageData\app\Listeners\RecordOrder;

trait PackageEventsTest
{
    /**
     * Configure the packager instance
     */
    public function configure(Packager $packager): void
    {
        $packager
            ->name('Test Package')
            ->hasEvent(OrderPlaced::class, RecordOrder::class)
            ->hasSubscriber(OrderSubscriber::class);
    }
}

uses(PackageEventsTest::class);

beforeEach(function () {
    RecordOrder::$handled = [];
    OrderSubscriber::$handled = [];
});

test('registered listener handles a dispatched package event', function () {
    event(new OrderPlaced('a1'));

    expect(RecordOrder::$handled)->toBe(['a1']);
});

test('registered subscriber handles a dispatched package event', function () {
    event(new OrderPlaced('b2'));

    expect(OrderSubscriber::$handled)->toBe(['b2']);
});
