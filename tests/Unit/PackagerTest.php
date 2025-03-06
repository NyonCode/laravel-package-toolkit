<?php

namespace NyonCode\LaravelPackageToolkit\Tests;

use InvalidArgumentException;
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\Tests\TestPackageData\commands\TestCommand;

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
    closure: fn() => expect($this->packager->hasVersion('1.0.1')->getVersion())
        ->not->toBeEmpty()
        ->toBe('1.0.1')
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
        $this->packager->hasCommands(TestCommand::class)->isCommandable()
    )->toBeTrue()
);

test(
    description: 'can access to view component with namespaces combined',
    closure: fn() => expect(
        $this->packager
            ->hasComponentNamespace('component1', '\\Test\\Component1')
            ->hasComponentNamespaces([
                'component3' => '\\Test\\Component3',
                'component4' => '\\Test\\Component4',
            ])
            ->viewComponentNamespaces()
    )
        ->toBeArray()
        ->toMatchArray([
            'component1' => '\\Test\\Component1',
            'component3' => '\\Test\\Component3',
            'component4' => '\\Test\\Component4',
        ])
        ->not->toMatchArray(['component2' => '\\Test\\Component2'])
);

test(
    description: 'can access to view component with namespaces',
    closure: fn() => expect(
        $this->packager
            ->hasComponentNamespaces([
                'component1' => '\\Test\\Component1',
                'component4' => '\\Test\\Component4',
            ])
            ->viewComponentNamespaces()
    )
        ->toBeArray()
        ->toMatchArray([
            'component1' => '\\Test\\Component1',
            'component4' => '\\Test\\Component4',
        ])
        ->not->toMatchArray(['component2' => '\\Test\\Component2'])
);

test(
    description: 'can access to view component namespace',
    closure: function () {
        $this->packager
            ->hasComponentNamespace('component1', '\\Test\\Component1')
            ->hasComponentNamespace('component2', '\\Test\\Component2');

        expect($this->packager->isViewComponentNamespaceConfigured())->toBeTrue();
    }
);

test(
    description: 'throws exception if shared data key is not a string',
    closure: function () {
        $this->packager->hasSharedDataForAllViews([
            0 => 'invalid',
        ]);
    }
)->throws(
    InvalidArgumentException::class,
    'The shared data key [0] must be a string.'
);

test(
    description: 'throws exception if shared data value is not a scalar, array, null, or an instance of Arrayable',
    closure: function () {
        $this->packager->hasSharedDataForAllViews([
            'callback' => fn() => 'invalid'
        ]);
    }
)->throws(
    exception:  InvalidArgumentException::class,
    exceptionMessage: 'The shared data value [callback] must be a scalar, array, null, or an instance of Arrayable.'
);

test(
    description: 'can set shared data for the views',
    closure: function () {
        $this->packager->hasSharedDataForAllViews([
            'key1' => 'value1',
            'key2' => 'value2',
        ]);

        expect($this->packager->isSharedWithViews())->toBeTrue();
    }
);

test(
    description: 'can get shared data for the views',
    closure: function () {
        $this->packager->hasSharedDataForAllViews([
            'key1' => 'value1',
            'key2' => 'value2',
        ]);

        expect($this->packager->viewSharedData())->toBeArray()->toMatchArray([
            'key1' => 'value1',
            'key2' => 'value2',
        ]);
    }
);

