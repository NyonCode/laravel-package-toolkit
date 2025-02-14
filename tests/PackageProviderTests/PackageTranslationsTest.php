<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

use NyonCode\LaravelPackageToolkit\Exceptions\InvalidLanguageDirectoryException;
use NyonCode\LaravelPackageToolkit\Packager;

trait PackageTranslationsTest
{

    /**
     * @throws InvalidLanguageDirectoryException
     */
    public function configure(Packager $packager): void
    {
        $packager->name('Test Package')->hasTranslations();
    }
}

uses(PackageTranslationsTest::class);

test('can register the translate files', fn() => expect(trans('test-package::message.text'))->toBe('Translate text'));
test('can register the translate json file', fn() => expect(trans('originalText'))->toBe('translateText'));

test('can publish the translation files', function () {
    $this->artisan('vendor:publish --tag=test-package::translations');

    expect(lang_path('vendor/test-package/en/message.php'))->toBeFile();
});

