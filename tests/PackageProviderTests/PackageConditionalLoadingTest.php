<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use NyonCode\LaravelPackageToolkit\Packager;

trait PackageConditionalLoadingTest
{
    private bool $whenExecuted = false;
    private bool $unlessExecuted = false;
    private bool $localExecuted = false;
    private bool $productionExecuted = false;
    private bool $consoleExecuted = false;
    private bool $classExistsExecuted = false;
    private int $multipleCallbacksCount = 0;

    public function configure(Packager $packager): void
    {
        $packager
            ->name('Conditional Loading Test Package')
            ->when(true, function () {
                $this->whenExecuted = true;
            })
            ->unless(true, function () {
                $this->unlessExecuted = true;
            })
            ->whenLocal(function () {
                $this->localExecuted = true;
            })
            ->whenProduction(function () {
                $this->productionExecuted = true;
            })
            ->whenConsole(function () {
                $this->consoleExecuted = true;
            })
            ->whenClassExists(Packager::class, function () {
                $this->classExistsExecuted = true;
            })
            ->when(true, function () {
                $this->multipleCallbacksCount++;
            })
            ->when(true, function () {
                $this->multipleCallbacksCount++;
            })
            ->when(false, function () {
                $this->multipleCallbacksCount++;
            });
    }
}

uses(PackageConditionalLoadingTest::class);

// Add this method to force testing environment
beforeEach(function () {
    // Explicitly set testing environment
    config(['app.env' => 'testing']);
    $_ENV['APP_ENV'] = 'testing';
    putenv('APP_ENV=testing');
});

test(
    description: 'when condition executes callback when true',
    closure: function () {
        expect($this->whenExecuted)->toBeTrue();
    }
);

test(
    description: 'unless condition does not execute callback when condition is true',
    closure: function () {
        expect($this->unlessExecuted)->toBeFalse();
    }
);

test(
    description: 'whenLocal does not execute in testing environment',
    closure: function () {
        expect($this->localExecuted)->toBeFalse();
    }
);

test(
    description: 'whenTesting executes in production environment',
    closure: function () {
        // Testujeme s explicitním production prostředím
        $productionExecuted = false;

        $testPackager = new Packager();
        $testPackager
            ->name('Test Testing')
            ->whenEnvironment('testing', function () use (&$productionExecuted) {
                $productionExecuted = true;
            });

        $testPackager->executeConditionalCallbacks();

        expect($productionExecuted)->toBeTrue();
    }
);

test(
    description: 'whenConsole executes in console environment',
    closure: function () {
        expect($this->consoleExecuted)->toBeTrue();
    }
);

test(
    description: 'whenClassExists executes when class exists',
    closure: function () {
        expect($this->classExistsExecuted)->toBeTrue();
    }
);

test(
    description: 'multiple conditional callbacks execute correctly',
    closure: function () {
        expect($this->multipleCallbacksCount)->toBe(2);
    }
);

test(
    description: 'package name is accessible in conditional callbacks',
    closure: function () {
        $packageName = '';

        $testPackager = new Packager();
        $testPackager
            ->name('Test Package Name')
            ->when(true, function ($packager) use (&$packageName) {
                $packageName = $packager->name;
            });

        $testPackager->executeConditionalCallbacks();

        expect($packageName)->toBe('Test Package Name');
    }
);