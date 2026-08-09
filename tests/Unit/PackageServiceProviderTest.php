<?php

namespace NyonCode\LaravelPackageToolkit\Tests\Unit;

use Illuminate\Console\Application as ConsoleApplication;
use Illuminate\Foundation\Application;
use Mockery;
use NyonCode\LaravelPackageToolkit\Exceptions\InvalidReturnTypeException;
use NyonCode\LaravelPackageToolkit\Exceptions\MissingNameException;
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\PackageServiceProvider;
use NyonCode\LaravelPackageToolkit\Tests\TestCase;
use NyonCode\LaravelPackageToolkit\Tests\TestPackageData\src\Commands\TestCommand;
use ReflectionClass;

uses(TestCase::class);

/**
 * A provider configured by the closure the test hands it. It lives in this file on
 * purpose: `getPackageBaseDir()` reflects on the provider class, so the package base
 * path is this directory and every fixture path below is relative to it.
 */
function provider(mixed $app, ?callable $using = null): PackageServiceProvider
{
    return new class($app, $using) extends PackageServiceProvider
    {
        public function __construct($app, private $using = null)
        {
            parent::__construct($app);
        }

        public function configure(Packager $packager): void
        {
            ($this->using ?? fn () => null)($packager);
        }
    };
}

function queuedConsoleBootstrappers(): int
{
    $bootstrappers = (new ReflectionClass(ConsoleApplication::class))
        ->getStaticPropertyValue('bootstrappers');

    return count($bootstrappers);
}

// Defaults
test('a provider boots a fresh packager', function () {
    $provider = provider($this->app);

    expect($provider->bootPackager())->toBeInstanceOf(Packager::class)
        ->and($provider->bootPackager())->not->toBe($provider->bootPackager());
});

test('the package base directory is the directory of the provider class', function () {
    expect(provider($this->app)->getPackageBaseDir())->toBe(__DIR__);
});

test('a provider declares no commands and no about data by default', function () {
    $provider = provider($this->app);

    expect($provider->packageCommands())->toBe([])
        ->and($provider->aboutData())->toBe([]);
});

// Command registration
test('package commands are registered while running in the console', function () {
    $provider = provider($this->app, fn (Packager $packager) => $packager
        ->name('Test Package')
        ->hasCommands(TestCommand::class)
    );
    $provider->register();

    $before = queuedConsoleBootstrappers();
    $provider->registerPackageCommands();

    expect(queuedConsoleBootstrappers())->toBe($before + 1);
});

test('nothing is registered outside the console', function () {
    $app = Mockery::mock(Application::class);
    $app->shouldReceive('runningInConsole')->once()->andReturnFalse();

    $before = queuedConsoleBootstrappers();
    provider($app)->registerPackageCommands();

    expect(queuedConsoleBootstrappers())->toBe($before);
});

// Validation
test('a package without a name is rejected', function () {
    expect(fn () => provider($this->app)->register())
        ->toThrow(MissingNameException::class, 'This package does not have a name');
});

test('a config file that does not return an array is rejected', function () {
    $provider = provider($this->app, fn (Packager $packager) => $packager
        ->name('Test Package')
        ->hasConfig(directory: '../TestPackageData/invalidConfig')
    );

    expect(fn () => $provider->register())
        ->toThrow(InvalidReturnTypeException::class, 'Configuration file [broken] must return an array.');
});

test('a valid config file is merged into the application config', function () {
    $provider = provider($this->app, fn (Packager $packager) => $packager
        ->name('Test Package')
        ->hasConfig('test-config.php', '../TestPackageData/config')
    );
    $provider->register();

    expect(config('test-config'))->toBeArray()->not->toBeEmpty();
});

// Automatic installation
test('the automatic installation is skipped without an install command', function () {
    $provider = provider($this->app, fn (Packager $packager) => $packager
        ->name('Test Package')
        ->installOnRun()
    );
    $provider->register();

    $silently = (new ReflectionClass($provider))->getMethod('performSilentInstallation');

    // There is no registered command to call: this must not reach Artisan at all.
    expect(fn () => $silently->invoke($provider))->not->toThrow(\Throwable::class);
});

// About command
test('the toolkit section is added to the about command only once', function () {
    $provider = provider($this->app);
    $register = (new ReflectionClass($provider))->getMethod('registerAboutCommand');

    $register->invoke($provider);
    $register->invoke($provider);

    $registered = (new ReflectionClass(PackageServiceProvider::class))
        ->getStaticPropertyValue('isPackageAboutRegistered');

    expect($registered)->toBeTrue();
});

// Deprecated alias
test('bootVewComposers still forwards to bootViewComposers', function () {
    $provider = provider($this->app, fn (Packager $packager) => $packager
        ->name('Test Package')
        ->hasViewComposer('test-page', fn () => null)
    );
    $provider->register();

    expect($provider->bootVewComposers())->toBe($provider);
});

// Version reporting
test('the toolkit version is reported for the about command', function () {
    $provider = provider($this->app);

    $version = (new ReflectionClass($provider))
        ->getMethod('getToolkitVersion')
        ->invoke($provider);

    expect($version)->toBeString()->not->toBeEmpty();
});
