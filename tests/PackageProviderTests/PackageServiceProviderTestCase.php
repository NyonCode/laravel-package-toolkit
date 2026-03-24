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
        ServiceProvider::$publishes = [];
        ServiceProvider::$publishGroups = [];

        $laravelProviderReflection = new ReflectionClass(ServiceProvider::class);
        $publishableMigrationsProperty = $laravelProviderReflection->getProperty('publishableMigrationPaths');
        $publishableMigrationsProperty->setValue(null, []);

        $toolkitProviderReflection = new ReflectionClass(PackageServiceProvider::class);
        $aboutRegisteredProperty = $toolkitProviderReflection->getProperty('isPackageAboutRegistered');
        $aboutRegisteredProperty->setValue(null, false);
    }
}
