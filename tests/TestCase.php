<?php

namespace NyonCode\LaravelPackageToolkit\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Testing\TestResponse;
use NyonCode\LaravelPackageToolkit\Packager;

class TestCase extends \Orchestra\Testbench\TestCase
{
    /**
     * Compatibility with older Orchestra Testbench versions used by prefer-lowest.
     *
     * @var TestResponse|null
     */
    protected static ?TestResponse $latestResponse;

    protected Packager $packager;

    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Set up the environment.
     *
     * This method is called before each test.
     *
     * @param  Application  $app  The application instance.
     *
     * @throws \Random\RandomException
     */
    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }
}
