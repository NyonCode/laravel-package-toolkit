<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use Illuminate\Support\Facades\File;
use NyonCode\LaravelPackageToolkit\Commands\InstallCommand;
use NyonCode\LaravelPackageToolkit\Packager;

/**
 * Without an explicit URL the prompt falls back to the package's own composer.json.
 */
trait PackageInstallStarRepoTest
{
    public function configure(Packager $packager): void
    {
        $packager->name('Test Package')
            ->hasInstallCommand(
                fn (InstallCommand $command) => $command->askToStarRepoOnGitHub()
            );
    }
}

uses(PackageInstallStarRepoTest::class);

beforeEach(function () {
    $this->composerPath = __DIR__.'/../TestPackageData/composer.json';
});

afterEach(function () {
    File::delete($this->composerPath);
});

test('nothing is asked when no repository can be determined', function () {
    $this->artisan('test-package:install')
        ->doesntExpectOutputToContain('star this package')
        ->assertSuccessful();
});

test('the repository falls back to the homepage in composer.json', function () {
    File::put($this->composerPath, json_encode([
        'name' => 'acme/blog',
        'homepage' => 'https://github.com/acme/blog',
    ]));

    $this->artisan('test-package:install')
        ->expectsConfirmation('⭐ Would you like to star this package on GitHub?', 'no')
        ->assertSuccessful();
});

test('the repository falls back to the support source in composer.json', function () {
    File::put($this->composerPath, json_encode([
        'name' => 'acme/blog',
        'support' => ['source' => 'https://github.com/acme/blog'],
    ]));

    $this->artisan('test-package:install')
        ->expectsConfirmation('⭐ Would you like to star this package on GitHub?', 'no')
        ->assertSuccessful();
});

test('a composer.json without a repository asks nothing', function () {
    File::put($this->composerPath, json_encode(['name' => 'acme/blog']));

    $this->artisan('test-package:install')
        ->doesntExpectOutputToContain('star this package')
        ->assertSuccessful();
});
