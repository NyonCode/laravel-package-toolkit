<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use Illuminate\Support\Facades\File;
use NyonCode\LaravelPackageToolkit\Packager;

trait PackageStubsPublishTest
{
    public function configure(Packager $packager): void
    {
        $packager->name('Test package')
            ->hasStubs(['test-command.stub']);
    }
}

uses(PackageStubsPublishTest::class);

afterEach(function () {
    File::deleteDirectory(base_path('stubs/test-package'));
});

test('can publish the package stubs', function () {
    $this->artisan('vendor:publish --tag=test-package::stubs')->assertExitCode(0);

    expect(base_path('stubs/test-package/test-command.stub'))->toBeFile();
});

test('stubs keep their extension and stay out of the shared stubs directory', function () {
    $this->artisan('vendor:publish --tag=test-package::stubs')->assertExitCode(0);

    expect(base_path('stubs/test-command.stub'))->not->toBeFile()
        ->and(file_get_contents(base_path('stubs/test-package/test-command.stub')))
        ->toContain('{{ class }}');
});
