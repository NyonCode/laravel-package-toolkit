<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use Illuminate\Support\Facades\File;
use NyonCode\LaravelPackageToolkit\Commands\InstallCommand;
use NyonCode\LaravelPackageToolkit\Packager;

trait PackageInstallProviderRegistrationTest
{
    public function configure(Packager $packager): void
    {
        $packager->name('Test Package')
            ->hasInstallCommand(
                fn (InstallCommand $command) => $command
                    ->copyAndRegisterServiceProviderInApp('Acme\Blog\BlogServiceProvider')
            );
    }
}

uses(PackageInstallProviderRegistrationTest::class);

beforeEach(function () {
    // The registration writes into `config/app.php`, so it gets a directory of its own
    // rather than the shared skeleton every other test reads from.
    $this->configPath = sys_get_temp_dir().'/lpt-config-'.getmypid();
    File::ensureDirectoryExists($this->configPath);

    $this->app->useConfigPath($this->configPath);
});

afterEach(function () {
    File::deleteDirectory($this->configPath);
});

function writeAppConfig(string $path, string $contents): void
{
    File::put($path.'/app.php', $contents);
}

test('a missing config/app.php is reported and skipped', function () {
    $this->artisan('test-package:install', ['--no-interaction' => true])
        ->expectsOutputToContain('config/app.php not found')
        ->assertSuccessful();
});

test('the provider is appended to the providers array', function () {
    writeAppConfig($this->configPath, <<<'PHP'
        <?php

        return [
            'providers' => [
                Illuminate\Support\ServiceProvider::class,
            ],
        ];
        PHP);

    $this->artisan('test-package:install', ['--no-interaction' => true])
        ->expectsOutputToContain('Service provider registered in config/app.php')
        ->assertSuccessful();

    expect(File::get($this->configPath.'/app.php'))
        ->toContain('Acme\Blog\BlogServiceProvider::class')
        ->toContain('Illuminate\Support\ServiceProvider::class');
});

test('an already registered provider is left alone', function () {
    $contents = <<<'PHP'
        <?php

        return [
            'providers' => [
                Acme\Blog\BlogServiceProvider::class,
            ],
        ];
        PHP;

    writeAppConfig($this->configPath, $contents);

    $this->artisan('test-package:install', ['--no-interaction' => true])
        ->expectsOutputToContain('Service provider already registered')
        ->assertSuccessful();

    expect(File::get($this->configPath.'/app.php'))->toBe($contents);
});

test('a config without a providers array is reported', function () {
    writeAppConfig($this->configPath, <<<'PHP'
        <?php

        return [
            'name' => 'Laravel',
        ];
        PHP);

    $this->artisan('test-package:install', ['--no-interaction' => true])
        ->expectsOutputToContain('Could not automatically register service provider')
        ->assertSuccessful();
});
