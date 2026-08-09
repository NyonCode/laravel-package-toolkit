<?php

namespace NyonCode\LaravelPackageToolkit\Tests\Unit;

use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\Tests\TestCase;
use RuntimeException;

uses(TestCase::class);

beforeEach(function () {
    $this->packager = (new Packager())->name('Test Package');
});

// Queueing
test('nothing is queued and nothing has run on a fresh packager', function () {
    expect($this->packager->getPendingConditionalCallbacksCount())->toBe(0)
        ->and($this->packager->conditionalCallbacksExecuted())->toBeFalse();
});

test('whenMultiple queues only the callbacks whose condition holds', function () {
    $this->packager->whenMultiple([
        ['condition' => true, 'callback' => fn () => null],
        ['condition' => false, 'callback' => fn () => null],
        ['condition' => true, 'callback' => fn () => null],
    ]);

    expect($this->packager->getPendingConditionalCallbacksCount())->toBe(2);
});

test('whenMultiple skips malformed entries', function () {
    $this->packager->whenMultiple([
        ['condition' => true],
        ['callback' => fn () => null],
        [],
    ]);

    expect($this->packager->getPendingConditionalCallbacksCount())->toBe(0);
});

test('whenMultiple is chainable', function () {
    expect($this->packager->whenMultiple([]))->toBe($this->packager);
});

test('whenFunctionExists queues only for a defined function', function () {
    $this->packager
        ->whenFunctionExists('array_map', fn () => null)
        ->whenFunctionExists('a_function_that_does_not_exist', fn () => null);

    expect($this->packager->getPendingConditionalCallbacksCount())->toBe(1);
});

test('whenExtensionLoaded queues only for a loaded extension', function () {
    $this->packager
        ->whenExtensionLoaded('json', fn () => null)
        ->whenExtensionLoaded('an_extension_that_is_not_loaded', fn () => null);

    expect($this->packager->getPendingConditionalCallbacksCount())->toBe(1);
});

// Execution
test('executing runs every queued callback with the packager', function () {
    $seen = [];

    $this->packager
        ->when(true, function (Packager $packager) use (&$seen) {
            $seen[] = $packager->name;
        })
        ->when(true, function () use (&$seen) {
            $seen[] = 'second';
        })
        ->executeConditionalCallbacks();

    expect($seen)->toBe(['Test Package', 'second'])
        ->and($this->packager->conditionalCallbacksExecuted())->toBeTrue()
        ->and($this->packager->getPendingConditionalCallbacksCount())->toBe(0);
});

test('callbacks are not run twice', function () {
    $runs = 0;

    $this->packager->when(true, function () use (&$runs) {
        $runs++;
    });

    $this->packager->executeConditionalCallbacks();
    $this->packager->executeConditionalCallbacks();

    expect($runs)->toBe(1);
});

test('a callback that throws does not stop the ones after it', function () {
    $ran = false;

    $this->packager
        ->when(true, fn () => throw new RuntimeException('boom'))
        ->when(true, function () use (&$ran) {
            $ran = true;
        })
        ->executeConditionalCallbacks();

    expect($ran)->toBeTrue()
        ->and($this->packager->conditionalCallbacksExecuted())->toBeTrue();
});

// Reset
test('resetting clears the queue and the executed flag', function () {
    $this->packager
        ->when(true, fn () => null)
        ->executeConditionalCallbacks()
        ->resetConditionalCallbacks();

    expect($this->packager->conditionalCallbacksExecuted())->toBeFalse()
        ->and($this->packager->getPendingConditionalCallbacksCount())->toBe(0);
});

test('a callback queued after a reset runs again', function () {
    $runs = 0;
    $callback = function () use (&$runs) {
        $runs++;
    };

    $this->packager->when(true, $callback)->executeConditionalCallbacks();
    $this->packager->resetConditionalCallbacks()->when(true, $callback)->executeConditionalCallbacks();

    expect($runs)->toBe(2);
});

// Environment
test('the environment comes from the application', function () {
    $this->packager->whenEnvironment('testing', fn () => null);

    expect($this->packager->getPendingConditionalCallbacksCount())->toBe(1);
});
