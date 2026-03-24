<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\PackageServiceProvider;
use NyonCode\LaravelPackageToolkit\Tests\TestCase;
use NyonCode\LaravelPackageToolkit\Tests\TestPackageData\src\TestServiceProvider;
use ReflectionClass;
use ReflectionException;

abstract class PackageServiceProviderTestCase extends TestCase
{
    protected function setUp(): void
    {
        $this->resetServiceProviderState();
        TestServiceProvider::$providerUsing = fn (Packager $packager) => $this->configure($packager);
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
        foreach (File::files(__DIR__.'/../TestPackageData/config') as $file) {
            $configPath = config_path($file->getFilename());

            if (file_exists($configPath)) {
                @unlink($configPath);
            }
        }

        $databaseMigrationsPath = database_path('migrations');

        if (! is_dir($databaseMigrationsPath)) {
            return;
        }

        $knownMigrationNames = collect(File::allFiles(__DIR__.'/../TestPackageData/database'))
            ->filter(fn ($file) => $file->getExtension() === 'php')
            ->map(fn ($file) => $file->getFilename())
            ->values();

        foreach (File::files($databaseMigrationsPath) as $migration) {
            $publishedName = $migration->getFilename();

            $shouldDelete = $knownMigrationNames->contains(function (string $knownName) use ($publishedName): bool {
                return $publishedName === $knownName
                    || Str::endsWith($publishedName, '_'.$knownName);
            });

            if ($shouldDelete) {
                @unlink($migration->getPathname());
            }
        }
    }

    protected function resetServiceProviderState(): void
    {
        $this->resetStaticProperty(ServiceProvider::class, 'publishes', []);
        $this->resetStaticProperty(ServiceProvider::class, 'publishGroups', []);
        $this->resetStaticProperty(ServiceProvider::class, 'publishableMigrationPaths', []);
        $this->resetStaticProperty(PackageServiceProvider::class, 'isPackageAboutRegistered', false);
    }

    /**
     * Reset static property when available (Laravel version compatible).
     *
     * @param  class-string  $class
     *
     * @throws ReflectionException
     */
    private function resetStaticProperty(string $class, string $property, mixed $value): void
    {
        $reflection = new ReflectionClass($class);

        if (! $reflection->hasProperty($property)) {
            return;
        }

        $propertyReflection = $reflection->getProperty($property);

        if (! $propertyReflection->isStatic()) {
            return;
        }

        $propertyReflection->setValue(null, $value);
    }
}
