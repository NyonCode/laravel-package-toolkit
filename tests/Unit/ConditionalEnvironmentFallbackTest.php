<?php

namespace NyonCode\LaravelPackageToolkit\Tests\Unit;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use NyonCode\LaravelPackageToolkit\Concerns\HasConditionalLoading;

/**
 * `getCurrentEnvironment()` falls back through `app()`, `config()` and finally the
 * environment variables, so the package can still be configured outside a booted
 * application. Without a Laravel application in the container the first two steps
 * throw, which is exactly what these tests set up.
 */
function environmentProbe(): object
{
    return new class()
    {
        use HasConditionalLoading;

        public function environment(): string
        {
            return $this->getCurrentEnvironment();
        }
    };
}

beforeEach(function () {
    $this->previousContainer = Container::getInstance();
    $this->previousEnv = $_ENV;
    $this->previousShellEnv = [
        'APP_ENV' => getenv('APP_ENV'),
        'ENVIRONMENT' => getenv('ENVIRONMENT'),
    ];

    // A bare container has no `environment()` and no `config` binding.
    Container::setInstance(new Container());
    Facade::clearResolvedInstances();
    Facade::setFacadeApplication(null);

    unset($_ENV['APP_ENV'], $_ENV['ENVIRONMENT']);
    putenv('APP_ENV');
    putenv('ENVIRONMENT');
});

afterEach(function () {
    $_ENV = $this->previousEnv;

    foreach ($this->previousShellEnv as $name => $value) {
        $value === false ? putenv($name) : putenv("$name=$value");
    }

    Container::setInstance($this->previousContainer);

    if ($this->previousContainer !== null) {
        Facade::setFacadeApplication($this->previousContainer);
    }
});

test('without an application the environment comes from APP_ENV', function () {
    $_ENV['APP_ENV'] = ' Staging ';

    expect(environmentProbe()->environment())->toBe('staging');
});

test('ENVIRONMENT is used when APP_ENV is absent', function () {
    $_ENV['ENVIRONMENT'] = 'ci';

    expect(environmentProbe()->environment())->toBe('ci');
});

test('a bound config repository is the next fallback', function () {
    Container::getInstance()->instance('config', new Repository(['app' => ['env' => 'from-config']]));

    expect(environmentProbe()->environment())->toBe('from-config');
});

test('an empty config value falls through to the environment variables', function () {
    Container::getInstance()->instance('config', new Repository(['app' => ['env' => '']]));
    $_ENV['APP_ENV'] = 'staging';

    expect(environmentProbe()->environment())->toBe('staging');
});

test('the environment defaults to production when nothing says otherwise', function () {
    expect(environmentProbe()->environment())->toBe('production');
});

test('whenProduction queues without an application', function () {
    $_ENV['APP_ENV'] = 'production';

    $probe = environmentProbe();
    $probe->whenProduction(fn () => null);
    $probe->whenLocal(fn () => null);

    expect($probe->getPendingConditionalCallbacksCount())->toBe(1);
});

test('whenLocal also accepts the development environment', function () {
    $_ENV['APP_ENV'] = 'development';

    $probe = environmentProbe();
    $probe->whenLocal(fn () => null);

    expect($probe->getPendingConditionalCallbacksCount())->toBe(1);
});
