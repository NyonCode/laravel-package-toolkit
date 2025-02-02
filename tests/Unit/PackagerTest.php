<?php

namespace NyonCode\LaravelPackageToolkit\Tests;

use NyonCode\LaravelPackageToolkit\Exceptions\InvalidComponentClass;
use NyonCode\LaravelPackageToolkit\Exceptions\InvalidComponentName;
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\Tests\TestPackageData\commands\TestCommand;
use stdClass;

beforeEach(function () {
    $this->packager = new Packager();
    $this->packager->name('Test Package');
});

test(
    description: 'can get name',
    closure: fn() => expect($this->packager->name)
        ->not()
        ->toBeEmpty()
        ->toBe('Test Package')
);

test(
    description: 'can get short name',
    closure: fn() => expect($this->packager->shortName())
        ->not()
        ->toBeEmpty()
        ->toBe('test-package')
);

test(
    description: 'can get custom short name',
    closure: fn() => expect($this->packager->hasShortName('tp-pg')->shortName())
        ->not->toBeEmpty()
        ->toBe('tp-pg')
);

test(
    description: 'can validate components',
    closure: fn() => expect(
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
        ->toThrow(InvalidComponentName::class)
        ->and(
            fn() => $this->packager->validateComponents([
                'component1' => 'not an object',
            ])
        )
        ->toThrow(InvalidComponentClass::class)
        ->and(
            fn() => $this->packager->validateComponents([
                'component1' => new stdClass(),
                1 => new stdClass(),
            ])
        )
        ->toThrow(InvalidComponentName::class)
);

test(
    description: 'can set AboutCommand ',
    closure: fn() => expect($this->packager->hasAbout()->isAboutable())
        ->toBeTrue()
        ->and(
            fn() => expect(
                $this->packager->hasAbout(false)->isAboutable()
            )->toBeFalse()
        )
);

test(
    description: 'can set AboutCommand version',
    closure: fn() => expect(
        $this->packager->hasVersion('1.0.1')->getVersion()
    )->not->toBeEmpty()->toBe('1.0.1')
);

test(
    description: 'can set migrations on run',
    closure: fn() => expect(
        $this->packager->canLoadMigrations()->hasMigrationsOnRun
    )->toBeTrue()
);

test(
    description: 'can set command',
    closure: fn() => expect(
        $this->packager->hasCommands(TestCommand::class)->isCommandable
    )->toBeTrue()

);
