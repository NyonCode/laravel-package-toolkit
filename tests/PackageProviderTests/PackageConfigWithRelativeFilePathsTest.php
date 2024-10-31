<?php

namespace NyonCode\LaravelPackageBuilder\Tests\PackageProviderTests;

use Exception;
use NyonCode\LaravelPackageBuilder\Packager;

trait PackageConfigWithRelativeFilePathsTest
{
    /**
     * @throws Exception
     */
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
