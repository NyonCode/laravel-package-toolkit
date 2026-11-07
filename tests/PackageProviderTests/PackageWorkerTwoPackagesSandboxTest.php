<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use Illuminate\Events\EventServiceProvider;
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\Tests\SecondPackage\SecondServiceProvider;
use NyonCode\LaravelPackageToolkit\Tests\TestPackageData\src\TestServiceProvider;

trait PackageWorkerTwoPackagesSandboxTest
{
    protected function getPackageProviders($app): array
    {
        return [TestServiceProvider::class, SecondServiceProvider::class];
    }

    protected function configurationBeforeProviders(): array
    {
        return ['package-toolkit.octane.enabled' => true];
    }

    public function configure(Packager $packager): void
    {
        $packager->name('Test Package');
    }
}

uses(PackageWorkerTwoPackagesSandboxTest::class);

test('every toolkit provider follows the operation, and none that is not', function () {
    $toolkit = [app()->getProvider(TestServiceProvider::class), app()->getProvider(SecondServiceProvider::class)];
    $framework = app()->getProvider(EventServiceProvider::class);

    [$during, $sandbox, $frameworkDuring] = octaneOperation(fn ($sandbox) => [
        array_map(fn ($provider) => providerApplication($provider), $toolkit),
        $sandbox,
        providerApplication($framework),
    ]);

    expect($during)->each->toBe($sandbox)
        ->and($frameworkDuring)->toBe(app())
        ->and(array_map(fn ($provider) => providerApplication($provider), $toolkit))->each->toBe(app());
});
