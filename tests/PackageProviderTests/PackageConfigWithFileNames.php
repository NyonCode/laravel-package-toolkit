<?php

namespace NyonCode\LaravelPackageBuilder\Tests\PackageProviderTests;

use Exception;
use NyonCode\LaravelPackageBuilder\Packager;

trait PackageConfigWithFileNames
{
    /**
     * @throws Exception
     */
    public function configure(Packager $packager): void
    {
        $packager->name('Test Package')
            ->hasConfig('test-config.php');
    }
}

uses(PackageConfigWithFileNames::class);

test('can access to config file', function () {
    expect(config('test-config.key1'))->not->toBeNull()->toBe('value1');
});


