<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use NyonCode\LaravelPackageToolkit\Commands\InstallCommand;
use NyonCode\LaravelPackageToolkit\Packager;

/**
 * Answering "yes" shells out to the platform's browser opener, so only the paths that
 * stay inside the process are exercised here.
 */
trait PackageInstallStarRepoUrlTest
{
    public function configure(Packager $packager): void
    {
        $packager->name('Test Package')
            ->hasInstallCommand(
                fn (InstallCommand $command) => $command
                    ->askToStarRepoOnGitHub('https://github.com/nyoncode/laravel-package-toolkit')
            );
    }
}

uses(PackageInstallStarRepoUrlTest::class);

test('nothing is asked when the command runs unattended', function () {
    $this->artisan('test-package:install', ['--no-interaction' => true])
        ->doesntExpectOutputToContain('star this package')
        ->assertSuccessful();
});

test('a declined answer is thanked instead', function () {
    $this->artisan('test-package:install')
        ->expectsConfirmation('⭐ Would you like to star this package on GitHub?', 'no')
        ->expectsOutputToContain('Thank you for using Test Package!')
        ->assertSuccessful();
});
