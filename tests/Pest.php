<?php

/*
|--------------------------------------------------------------------------
| Admin Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\ServiceProvider;
use Laravel\Octane\CurrentApplication;
use Laravel\Octane\Events\RequestReceived;
use Laravel\Octane\Events\RequestTerminated;
use Laravel\Octane\Events\TaskReceived;
use Laravel\Octane\Events\TaskTerminated;
use Laravel\Octane\Events\TickReceived;
use Laravel\Octane\Events\TickTerminated;
use NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests\PackageServiceProviderTestCase;

uses(PackageServiceProviderTestCase::class)->in('PackageProviderTests');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

/*expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});*/

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Whether the platform actually enforces the permission bits a test sets.
 *
 * Windows has no such bits — `chmod()` there only toggles the read-only attribute and
 * a file stays readable — and root is exempt from the ones it does have. A test that
 * makes something unreadable and expects to be stopped by it proves nothing in either
 * case, so it is skipped rather than left to fail.
 */
function permissionsAreEnforced(): bool
{
    if (PHP_OS_FAMILY === 'Windows') {
        return false;
    }

    return function_exists('posix_geteuid')
        ? posix_geteuid() !== 0
        : ! is_readable('/etc/sudoers');
}

/**
 * Run one operation the way an Octane worker does — the steps of
 * `Laravel\Octane\Worker::handle()`, with Octane's own events and its own
 * `CurrentApplication` switch, so the clone and what points at it are Octane's and not an
 * imitation of them. Only the HTTP kernel is left out: `$during` stands in for it.
 *
 * `$during` runs inside the operation, on the clone, and its return value is returned.
 * `$event` picks the operation: a request, a task or a tick.
 */
function octaneOperation(?Closure $during = null, string $event = 'request'): mixed
{
    $base = app();
    $sandbox = clone $base;
    $result = null;

    CurrentApplication::set($sandbox);

    try {
        if ($event === 'request') {
            $request = Request::create('/__octane-operation');
            $sandbox['events']->dispatch(new RequestReceived($base, $sandbox, $request));
            $result = $during ? $during($sandbox) : null;
            $response = new Response();
            $sandbox['events']->dispatch(new RequestTerminated($base, $sandbox, $request, $response));
        } elseif ($event === 'task') {
            $sandbox['events']->dispatch(new TaskReceived($base, $sandbox, null));
            $result = $during ? $during($sandbox) : null;
            $sandbox['events']->dispatch(new TaskTerminated($base, $sandbox, null, null));
        } else {
            $sandbox['events']->dispatch(new TickReceived($base, $sandbox));
            $result = $during ? $during($sandbox) : null;
            $sandbox['events']->dispatch(new TickTerminated($base, $sandbox));
        }
    } finally {
        $sandbox->flush();
        CurrentApplication::set($base);
    }

    return $result;
}

/**
 * The application a service provider currently holds as `$this->app`.
 */
function providerApplication(ServiceProvider $provider): mixed
{
    return (new ReflectionProperty(ServiceProvider::class, 'app'))->getValue($provider);
}
