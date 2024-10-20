<?php

namespace NyonCode\LaravelPackageBuilder\Tests\PackageProviderTests;

use Exception;
use NyonCode\LaravelPackageBuilder\Packager;

trait PackageSpecificConfigFileTest
{
    /**
     * @throws Exception
     */
    public function configure(Packager $packager): void
    {
        $packager->name('Test Package')->hasConfig('../config/test-config.php');

    }
}

uses(PackageSpecificConfigFileTest::class);


test('can register a specific configuration file', function () {
    expect(config('test-config.key2'))
        ->not()
        ->toBeEmpty()
        ->toBe('value2')
        ->and(config('alternative-config.alternative-key'))
        ->toBeNull();
});