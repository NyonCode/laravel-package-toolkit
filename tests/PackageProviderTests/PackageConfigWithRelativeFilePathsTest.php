<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use Exception;
use NyonCode\LaravelPackageToolkit\Packager;

trait PackageConfigWithRelativeFilePathsTest
{

    public function configure(Packager $packager): void
    {
        $packager
            ->name('Test Package')
            ->hasConfig(['../alternativeConfig/foo.php']);
    }
}

uses(PackageConfigWithRelativeFilePathsTest::class);

test(
    'can register the alternative config files',
    fn() => expect(config('foo.foo'))
        ->not->toBeNull()
        ->toBe('alt-bar')
        ->and(
            fn() => expect(config('test-config.key2'))
                ->not()
                ->toBe('value2')
                ->toBeEmpty()
        )
);
