<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use Illuminate\Support\Facades\File;
use NyonCode\LaravelPackageToolkit\Commands\InstallCommand;
use NyonCode\LaravelPackageToolkit\Packager;

/**
 * The install command publishes by tag, so it has to build the same tags the provider
 * registered — including a custom separator.
 */
trait PackageInstallTagSeparatorTest
{
    public function configure(Packager $packager): void
    {
        $packager->name('Test Package')
            ->hasPublishTagSeparator('-')
            ->hasConfig('test-config.php')
            ->hasInstallCommand(
                fn (InstallCommand $command) => $command->publishConfig()
            );
    }
}

uses(PackageInstallTagSeparatorTest::class);

afterEach(function () {
    File::delete(config_path('test-config.php'));
});

test('the install command publishes through the custom tag separator', function () {
    $this->artisan('test-package:install', ['--no-interaction' => true])
        ->assertSuccessful();

    expect(config_path('test-config.php'))->toBeFile();
});

test('the published step is reported when running interactively', function () {
    $this->artisan('test-package:install')
        ->expectsOutputToContain('Published config')
        ->assertSuccessful();

    expect(config_path('test-config.php'))->toBeFile();
});
