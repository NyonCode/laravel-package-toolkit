<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use NyonCode\LaravelPackageToolkit\Packager;

trait PackageMultiSeparatorPublishTest
{
    public function configure(Packager $packager): void
    {
        $packager->name('Test Package')
            ->hasPublishTagSeparator(['::', '-'])
            ->hasConfig('test-config.php');
    }
}

uses(PackageMultiSeparatorPublishTest::class);

test('registers the publish group under both tag forms', function () {
    expect(ServiceProvider::pathsToPublish(null, 'test-package::config'))->not->toBeEmpty()
        ->and(ServiceProvider::pathsToPublish(null, 'test-package-config'))->not->toBeEmpty();
});

test('publishes with the :: separator tag', function () {
    $this->artisan('vendor:publish --tag=test-package::config')->assertExitCode(0);

    expect(config_path('test-config.php'))->toBeFile();

    File::delete(config_path('test-config.php'));
});

test('publishes with the - separator tag', function () {
    $this->artisan('vendor:publish --tag=test-package-config')->assertExitCode(0);

    expect(config_path('test-config.php'))->toBeFile();

    File::delete(config_path('test-config.php'));
});
