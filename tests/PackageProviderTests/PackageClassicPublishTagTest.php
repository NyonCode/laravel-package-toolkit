<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use Illuminate\Support\ServiceProvider;
use NyonCode\LaravelPackageToolkit\Packager;

trait PackageClassicPublishTagTest
{
    public function configure(Packager $packager): void
    {
        $packager->name('Test Package')
            ->hasPublishTagSeparator('-')
            ->hasConfig('test-config.php');
    }
}

uses(PackageClassicPublishTagTest::class);

test('registers the publish group using the classic flat tag format', function () {
    expect(ServiceProvider::pathsToPublish(null, 'test-package-config'))
        ->not->toBeEmpty();
});

test('publishes using the classic flat tag format', function () {
    $this->artisan('vendor:publish --tag=test-package-config')->assertExitCode(0);

    expect(config_path('test-config.php'))->toBeFile();
});
