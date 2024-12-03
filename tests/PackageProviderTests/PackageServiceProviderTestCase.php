<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use Illuminate\Support\Facades\File;
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\Tests\TestCase;
use NyonCode\LaravelPackageToolkit\Tests\TestPackageData\src\TestServiceProvider;

abstract class PackageServiceProviderTestCase extends TestCase
{

    protected function setUp(): void
    {
        TestServiceProvider::$providerUsing = fn(Packager $packager) => $this->configure($packager);
        parent::setUp();

        $this->clear();
    }

    abstract protected function configure(Packager $packager): void;

    protected function getPackageProviders($app): array
    {
        return [
            TestServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'TestProvider' => TestServiceProvider::class,
        ];
    }

    protected function clear(): void
    {
        foreach (File::allFiles(__DIR__ . '/../TestPackageData/') as $file) {
            if (file_exists(config_path($file->getPathname()))) {
                unlink(config_path($file->getPathname()));
            }
        }
    }
}