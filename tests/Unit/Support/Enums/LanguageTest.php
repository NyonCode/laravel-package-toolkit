<?php

namespace NyonCode\LaravelPackageBuilder\Tests\Support\Enums;

use NyonCode\LaravelPackageBuilder\Support\Enums\Language;

test('can get language names', function () {
    $names = Language::names();

    expect($names)->not->toBeEmpty()
        ->and($names)->toContain('English', 'Czech', 'German');
});

test('can get language codes', function () {
    $codes = Language::codes();

    expect($codes)->not->toBeEmpty()
        ->and($codes)->toContain('cs', 'en', 'de');
});

test('can get language collection', function () {
    $collection = Language::collection();
    expect($collection)->not->toBeEmpty()
        ->and($collection->first())->toBeInstanceOf(Language::class);
});

test('language enum values are correct', function () {
    expect(Language::EN->value)->toBe('English')
        ->and(Language::CS->value)->toBe('Czech')
        ->and(Language::DE->value)->toBe('German');
});