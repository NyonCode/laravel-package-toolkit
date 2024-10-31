<?php

namespace NyonCode\LaravelPackageBuilder\Tests;

use NyonCode\LaravelPackageBuilder\Exceptions\PackagerException;
use NyonCode\LaravelPackageBuilder\Packager;
use stdClass;

beforeEach(function () {
    $this->packager = new Packager();
    $this->packager->name('Test Package');
});

test(
    description: 'can get name',
    closure: function () {

        expect($this->packager->name)
            ->not()
            ->toBeEmpty()
            ->toBe('Test Package');
    }
);

test(
    description: 'can get short name',
    closure: function () {
        expect($this->packager->shortName())
            ->not()
            ->toBeEmpty()
            ->toBe('test-package');
    }
);

test(
    description: 'can validate components',
    closure: function () {

        expect(
            $this->packager->validateComponents([
                'component1' => new stdClass(),
                'component2' => new stdClass(),
            ])
        )
            ->toBeTrue()
            ->and(
                fn() => $this->packager->validateComponents([
                    123 => new stdClass(),
                ])
            )
            ->toThrow(PackagerException::class)
            ->and(
                fn() => $this->packager->validateComponents([
                    'component1' => 'not an object'
                ])
            )->toThrow(PackagerException::class)
            ->and(
                fn() => $this->packager->validateComponents([
                    'component1' => new stdClass(),
                    1 => new stdClass(),
                ])
            )->toThrow(PackagerException::class);
    }
);
