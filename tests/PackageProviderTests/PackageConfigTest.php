<?php

namespace NyonCode\LaravelPackageBuilder\Tests\PackageProviderTests;

use Exception;
use Illuminate\Support\Facades\File;
use NyonCode\LaravelPackageBuilder\Packager;

trait PackageConfigTest
{
    /**
     * @throws Exception
     */
    public function configure(Packager $packager): void
    {
        $packager->name('Test Package')->hasConfig();
    }
}

uses(PackageConfigTest::class);

test(
    'can register the config files',
    fn() => expect(config('test-config.key1'))
        ->not()
        ->toBeEmpty()
        ->toBe('value1')
        ->and(fn() => expect(config('test-config.key2'))
            ->not()
            ->toBeEmpty()
            ->toBe('value2'))
        ->and(fn() => expect(config('alternative-config.alternative-key'))
            ->not()
            ->toBeEmpty()
            ->toBe('alternative-value'))
);

test('can publish the specific config file', function () {

    $this->artisan('vendor:publish --tag=test-package::config')->assertExitCode(0);

    foreach (File::files(__DIR__ . '/../TestPackageData/config') as $file) {
        expect(config_path($file->getFilename()))->toBeFile();
    }
});