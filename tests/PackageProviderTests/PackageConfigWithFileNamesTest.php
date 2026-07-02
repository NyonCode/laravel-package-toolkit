<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use NyonCode\LaravelPackageToolkit\Packager;

trait PackageConfigWithFileNamesTest
{
    public function configure(Packager $packager): void
    {
        $packager->name('Test Package')
            ->hasConfig('test-config.php');
    }
}

uses(PackageConfigWithFileNamesTest::class);

test('can access to config file', function () {
    expect(config('test-config.key1'))->not->toBeNull()->toBe('value1');
});
