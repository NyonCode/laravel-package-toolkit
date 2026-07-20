<?php

namespace NyonCode\LaravelPackageToolkit\Tests\Unit;

use NyonCode\LaravelPackageToolkit\Packager;

beforeEach(function () {
    $this->packager = (new Packager())->name('Test Package');
});

test('is not eventable by default', function () {
    expect($this->packager->isEventable())->toBeFalse()
        ->and($this->packager->events())->toBeEmpty()
        ->and($this->packager->subscribers())->toBeEmpty();
});

test('hasEvent registers a single listener and marks the package eventable', function () {
    $this->packager->hasEvent('order.placed', 'SendMail');

    expect($this->packager->isEventable())->toBeTrue()
        ->and($this->packager->events())->toBe(['order.placed' => ['SendMail']]);
});

test('hasEvent accepts an array of listeners', function () {
    $this->packager->hasEvent('order.placed', ['SendMail', 'LogOrder']);

    expect($this->packager->events()['order.placed'])->toBe(['SendMail', 'LogOrder']);
});

test('hasEvent accepts a closure listener', function () {
    $listener = fn () => null;

    $this->packager->hasEvent('order.placed', $listener);

    expect($this->packager->events()['order.placed'])->toBe([$listener]);
});

test('hasEvent merges listeners for the same event across calls', function () {
    $this->packager
        ->hasEvent('order.placed', 'A')
        ->hasEvent('order.placed', ['B', 'C']);

    expect($this->packager->events()['order.placed'])->toBe(['A', 'B', 'C']);
});

test('hasEvents registers multiple events at once', function () {
    $this->packager->hasEvents([
        'e1' => 'L1',
        'e2' => ['L2a', 'L2b'],
    ]);

    expect($this->packager->events())->toBe([
        'e1' => ['L1'],
        'e2' => ['L2a', 'L2b'],
    ]);
});

test('hasSubscriber registers a subscriber and marks the package eventable', function () {
    $this->packager->hasSubscriber('MySubscriber');

    expect($this->packager->isEventable())->toBeTrue()
        ->and($this->packager->subscribers())->toBe(['MySubscriber']);
});

test('hasSubscribers registers multiple subscribers', function () {
    $this->packager->hasSubscribers(['S1', 'S2']);

    expect($this->packager->subscribers())->toBe(['S1', 'S2']);
});
