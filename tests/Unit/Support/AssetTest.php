<?php

use NyonCode\LaravelPackageToolkit\Support\Asset;

test('a shipped file infers its kind from the extension', function () {
    expect(Asset::make('css/blog.css')->isStylesheet())->toBeTrue()
        ->and(Asset::make('js/blog.js')->isStylesheet())->toBeFalse()
        ->and(Asset::vite('resources/css/blog.scss')->isStylesheet())->toBeTrue();
});

test('an explicit kind overrides the extension', function () {
    expect(Asset::make('js/blog.js')->asStylesheet()->isStylesheet())->toBeTrue()
        ->and(Asset::make('css/blog.css')->asScript()->isStylesheet())->toBeFalse();
});

test('the entry key is the shipped file when there is one', function () {
    expect(Asset::vite('resources/js/blog.js')->fallback('js/blog.js')->key())->toBe('js/blog.js')
        ->and(Asset::vite('resources/js/blog.js')->key())->toBe('resources/js/blog.js');
});

test('scripts are modules unless opted out', function () {
    expect(Asset::make('js/blog.js')->isModule())->toBeTrue()
        ->and(Asset::make('js/blog.js')->classic()->isModule())->toBeFalse();
});

test('a classic script defers, a module does not need to', function () {
    expect(Asset::make('js/blog.js')->classic()->tagAttributes())->toHaveKey('defer')
        ->and(Asset::make('js/blog.js')->tagAttributes())->not->toHaveKey('defer');
});

test('navigate tracking is a default the caller can remove', function () {
    expect(Asset::make('css/blog.css')->tagAttributes())
        ->toBe(['data-navigate-track' => 'reload'])
        ->and(Asset::make('css/blog.css')->attributes(['data-navigate-track' => null])->tagAttributes())
        ->toBe(['data-navigate-track' => null]);
});

test('paths are normalized to forward slashes without a leading one', function () {
    expect(Asset::make('\\css\\blog.css')->file())->toBe('css/blog.css')
        ->and(Asset::make('/css/blog.css')->file())->toBe('css/blog.css');
});

test('a path that escapes the package is rejected', function () {
    expect(fn () => Asset::make('../../.env'))
        ->toThrow(InvalidArgumentException::class, 'must stay inside the package')
        ->and(fn () => Asset::vite('  '))
        ->toThrow(InvalidArgumentException::class, 'cannot be empty');
});
