<?php

namespace NyonCode\LaravelPackageToolkit\Tests\Unit;

use NyonCode\LaravelPackageToolkit\Packager;

test('separator is null by default', function () {
    expect((new Packager())->publishTagSeparator())->toBeNull();
});

test('hasPublishTagSeparator stores the separator', function () {
    expect((new Packager())->hasPublishTagSeparator('-')->publishTagSeparator())
        ->toBe('-');
});

test('hasPublishTagSeparator is chainable and the last call wins', function () {
    $packager = (new Packager())
        ->hasPublishTagSeparator('-')
        ->hasPublishTagSeparator('.');

    expect($packager->publishTagSeparator())->toBe('.');
});

test('separators is empty by default', function () {
    expect((new Packager())->publishTagSeparators())->toBeEmpty();
});

test('hasPublishTagSeparator accepts an array of separators', function () {
    $packager = (new Packager())->hasPublishTagSeparator(['::', '-']);

    expect($packager->publishTagSeparators())->toBe(['::', '-'])
        ->and($packager->publishTagSeparator())->toBe('::');
});

test('hasPublishTagSeparator de-duplicates separators', function () {
    $packager = (new Packager())->hasPublishTagSeparator(['-', '-', '::']);

    expect($packager->publishTagSeparators())->toBe(['-', '::']);
});
