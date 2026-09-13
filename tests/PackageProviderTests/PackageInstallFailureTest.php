<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

/**
 * What the install command does when a publish does not go through.
 *
 * It used to do nothing: the exit code came back from `vendor:publish`, was
 * compared against zero, printed about, and thrown away — `handle()` returned
 * SUCCESS either way, under a banner reading "installed successfully". A deploy
 * script checking the exit code of an installer had no way to learn that half
 * the package was not there.
 *
 * `vendor:publish` is stubbed here because Laravel's own returns `void`, so it
 * can never report a failure of its own. That is the reason this path went
 * unnoticed, and no reason for the toolkit to keep discarding an answer it asks
 * for.
 */

use Illuminate\Support\Facades\Artisan;
use NyonCode\LaravelPackageToolkit\Commands\InstallCommand;
use NyonCode\LaravelPackageToolkit\Packager;

trait PackageInstallFailureTest
{
    public function configure(Packager $packager): void
    {
        $packager->name('Test Package')
            ->hasConfig('test-config.php')
            ->hasMigrations()
            ->hasInstallCommand(
                fn (InstallCommand $command) => $command->publishConfig()->publishMigrations()
            );
    }
}

uses(PackageInstallFailureTest::class);

/**
 * Stand in for `vendor:publish`, failing only the tags named.
 *
 * @param  array<int, string>  $failing
 */
function stubVendorPublish(array $failing): void
{
    Artisan::command(
        'vendor:publish {--tag=*} {--force} {--all} {--provider=} {--existing}',
        function () use ($failing): int {
            return array_intersect((array) $this->option('tag'), $failing) !== [] ? 1 : 0;
        }
    );
}

test('a publish that fails fails the install, and names the tag', function () {
    stubVendorPublish(['test-package::migrations']);

    $this->artisan('test-package:install', ['--force' => true])
        ->expectsOutputToContain('Failed to publish migrations')
        ->expectsOutputToContain('was not fully installed — these did not publish: migrations')
        ->assertFailed();
});

test('the steps after the one that failed still run', function () {
    // An installer that abandons the run at the first failure leaves a package
    // half set up with nothing saying which half.
    stubVendorPublish(['test-package::config']);

    $this->artisan('test-package:install')
        ->expectsOutputToContain('Publishing migrations')
        ->assertFailed();
});

test('the failure is reported even when the run is unattended', function () {
    // The opposite of the success banner, which `--no-interaction` suppresses:
    // an unattended run is exactly where the only account of what went wrong
    // cannot be the thing that gets silenced.
    stubVendorPublish(['test-package::config']);

    $this->artisan('test-package:install', ['--no-interaction' => true])
        ->expectsOutputToContain('was not fully installed')
        ->doesntExpectOutputToContain('installed successfully')
        ->assertFailed();
});

test('a run where everything published still succeeds', function () {
    stubVendorPublish([]);

    $this->artisan('test-package:install', ['--force' => true])
        ->doesntExpectOutputToContain('was not fully installed')
        ->assertSuccessful();
});
