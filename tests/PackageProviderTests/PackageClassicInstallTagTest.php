<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use Illuminate\Support\Facades\File;
use NyonCode\LaravelPackageToolkit\Commands\InstallCommand;
use NyonCode\LaravelPackageToolkit\Packager;

trait PackageClassicInstallTagTest
{
    public function configure(Packager $packager): void
    {
        $packager->name('Test Package')
            ->hasPublishTagSeparator('-')
            ->hasConfig('test-config.php')
            ->hasInstallCommand(function (InstallCommand $command) {
                $command->publishConfig();
            });
    }
}

uses(PackageClassicInstallTagTest::class);

test('install command publishes using the configured tag separator', function () {
    $this->artisan('test-package:install', ['--no-interaction' => true, '--force' => true])
        ->assertExitCode(0);

    expect(config_path('test-config.php'))->toBeFile();

    File::delete(config_path('test-config.php'));
});
