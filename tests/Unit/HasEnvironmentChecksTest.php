<?php

namespace NyonCode\LaravelPackageToolkit\Tests\Unit;

use NyonCode\LaravelPackageToolkit\Support\Concerns\HasEnvironmentChecks;
use NyonCode\LaravelPackageToolkit\Tests\TestCase;

uses(TestCase::class);

function environmentChecks(): object
{
    return new class()
    {
        use HasEnvironmentChecks;

        public function inProduction(): bool
        {
            return $this->isInProduction();
        }

        public function inLocal(): bool
        {
            return $this->isInLocal();
        }
    };
}

test('neither check passes in the testing environment', function () {
    expect(environmentChecks()->inProduction())->toBeFalse()
        ->and(environmentChecks()->inLocal())->toBeFalse();
});

test('production is detected', function () {
    $this->app['env'] = 'production';

    expect(environmentChecks()->inProduction())->toBeTrue()
        ->and(environmentChecks()->inLocal())->toBeFalse();
});

test('local is detected', function () {
    $this->app['env'] = 'local';

    expect(environmentChecks()->inLocal())->toBeTrue()
        ->and(environmentChecks()->inProduction())->toBeFalse();
});
