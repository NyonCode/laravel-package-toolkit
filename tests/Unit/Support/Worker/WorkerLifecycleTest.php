<?php

use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Application;
use NyonCode\LaravelPackageToolkit\Support\Worker\WorkerLifecycle;
use NyonCode\LaravelPackageToolkit\Tests\TestCase;

uses(TestCase::class);

afterEach(function () {
    unset($_SERVER['LARAVEL_OCTANE']);
});

test('isolation config accepts names, a namespace string and a list of namespaces', function () {
    $lifecycle = new WorkerLifecycle(isolate: [
        'plain',
        'one' => 'Acme\\One',
        'many' => ['\\Acme\\Two\\', 'Acme\\Three\\'],
        '',
        42,
        'junk' => [null, 5, '\\'],
    ]);

    $lifecycle->isolate('plain', ['Provider\\Ns\\'], []);
    $lifecycle->isolate('one', ['Provider\\Ns\\'], []);
    $lifecycle->isolate('many', ['Provider\\Ns\\'], []);
    $lifecycle->isolate('junk', ['Provider\\Ns\\'], []);

    $namespaces = fn (string $package) => (new ReflectionProperty($lifecycle->isolations()[$package], 'namespaces'))
        ->getValue($lifecycle->isolations()[$package]);

    expect($lifecycle->isolates('plain'))->toBeTrue()
        ->and($lifecycle->isolates(''))->toBeFalse()
        ->and($lifecycle->isolates('42'))->toBeFalse()
        ->and($namespaces('plain'))->toBe(['Provider\\Ns\\'])
        ->and($namespaces('one'))->toBe(['Acme\\One\\'])
        ->and($namespaces('many'))->toBe(['Acme\\Two\\', 'Acme\\Three\\'])
        ->and($namespaces('junk'))->toBe(['Provider\\Ns\\']);
});

test('isolating the same package twice adds to one isolation', function () {
    $lifecycle = new WorkerLifecycle(isolate: ['plain']);

    $lifecycle->isolate('plain', ['A\\'], ['first']);
    $lifecycle->isolate('plain', ['A\\'], ['second']);

    $owned = (new ReflectionProperty($lifecycle->isolations()['plain'], 'owned'))->getValue($lifecycle->isolations()['plain']);

    expect($lifecycle->isolations())->toHaveCount(1)
        ->and($owned)->toBe(['first' => true, 'second' => true]);
});

test('configuration overrides what the environment says', function (mixed $configured, bool $octane, bool $expected) {
    $octane ? $_SERVER['LARAVEL_OCTANE'] = '1' : null;
    config(['package-toolkit.octane.enabled' => $configured]);

    expect(WorkerLifecycle::enabled(app()))->toBe($expected);
})->with([
    'not configured, classic' => [null, false, false],
    'not configured, octane' => [null, true, true],
    'forced off under octane' => [false, true, false],
    'forced on without octane' => [true, false, true],
]);

test('without a config repository the environment decides', function () {
    $_SERVER['LARAVEL_OCTANE'] = '1';

    expect(WorkerLifecycle::enabled(new Application(sys_get_temp_dir())))->toBeTrue();
});

test('an operation the worker never finished is replaced by the next', function () {
    $lifecycle = new WorkerLifecycle(sandboxEvents: true);
    $base = app();
    $first = clone $base;
    $second = clone $base;
    $container = fn () => (new ReflectionProperty(Dispatcher::class, 'container'))->getValue(app('events'));

    $lifecycle->operationStarting($base, $first);
    // No operationTerminated: the worker threw outside the kernel.
    $lifecycle->operationStarting($base, $second);

    expect($container())->toBe($second);

    $lifecycle->operationTerminated($base);

    expect($container())->toBe($base);
});

test('a worker without clones may pass the same application twice', function () {
    $lifecycle = new WorkerLifecycle(sandboxEvents: true, isolate: ['x']);
    $lifecycle->isolate('x', ['Nothing\\Here\\'], []);

    $lifecycle->operationStarting(app(), app());
    $lifecycle->operationTerminated(app());

    $container = (new ReflectionProperty(Dispatcher::class, 'container'))->getValue(app('events'));

    expect($container)->toBe(app())
        ->and($lifecycle->isolations()['x']->hasSnapshot())->toBeTrue();
});
