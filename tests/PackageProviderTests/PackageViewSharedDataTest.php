<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use NyonCode\LaravelPackageToolkit\Packager;
use View;

trait PackageViewSharedDataTest
{
    public function configure(Packager $packager): void
    {
        $packager
            ->name('Test Package')
            ->hasViews()
            ->hasSharedDataForAllViews([
                'foo' => 'bar',
                'baz' => 'qux',
            ]);
    }
}

uses(PackageViewSharedDataTest::class);

test(
    description: 'can add shared data',
    closure: function () {
        expect(View::shared('foo'))
            ->toBe('bar')
            ->and(View::shared('baz'))
            ->toBe('qux');
    }
);
