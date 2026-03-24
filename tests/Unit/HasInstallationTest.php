<?php

namespace NyonCode\LaravelPackageToolkit\Tests\Unit;

use Closure;
use NyonCode\LaravelPackageToolkit\Commands\InstallCommand;
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->packager = new Packager();
    $this->packager->name('Test Package');
});

// isInstallable()
test('isInstallable returns false by default', function () {
    expect($this->packager->isInstallable())->toBeFalse();
});

test('hasInstallCommand makes package installable', function () {
    $this->packager->hasInstallCommand();

    expect($this->packager->isInstallable())->toBeTrue();
});

test('hasInstallCommand with null callback stores null', function () {
    $this->packager->hasInstallCommand();

    expect($this->packager->getInstallCommandCallback())->toBeNull();
});

test('hasInstallCommand stores provided callback', function () {
    $this->packager->hasInstallCommand(function (InstallCommand $cmd) {
        $cmd->publishConfig();
    });

    expect($this->packager->getInstallCommandCallback())->toBeInstanceOf(Closure::class);
});

test('withoutInstallCommand disables installation', function () {
    $this->packager->hasInstallCommand()->withoutInstallCommand();

    expect($this->packager->isInstallable())->toBeFalse();
});

test('withoutInstallCommand clears callback', function () {
    $this->packager->hasInstallCommand(fn (InstallCommand $cmd) => $cmd->publishConfig());
    $this->packager->withoutInstallCommand();

    expect($this->packager->getInstallCommandCallback())->toBeNull();
});

// isInstallCommandHidden()
test('isInstallCommandHidden returns false by default', function () {
    expect($this->packager->isInstallCommandHidden())->toBeFalse();
});

test('installCommandHidden hides the install command', function () {
    $this->packager->installCommandHidden();

    expect($this->packager->isInstallCommandHidden())->toBeTrue();
});

test('installCommandHidden with explicit true hides command', function () {
    $this->packager->installCommandHidden(true);

    expect($this->packager->isInstallCommandHidden())->toBeTrue();
});

test('installCommandHidden with false keeps command visible', function () {
    $this->packager->installCommandHidden(false);

    expect($this->packager->isInstallCommandHidden())->toBeFalse();
});

test('installCommandHidden can be toggled on and off', function () {
    $this->packager->installCommandHidden(true);
    expect($this->packager->isInstallCommandHidden())->toBeTrue();

    $this->packager->installCommandHidden(false);
    expect($this->packager->isInstallCommandHidden())->toBeFalse();
});

// shouldInstallOnRun()
test('shouldInstallOnRun returns false by default', function () {
    expect($this->packager->shouldInstallOnRun())->toBeFalse();
});

test('installOnRun enables auto-installation', function () {
    $this->packager->installOnRun();

    expect($this->packager->shouldInstallOnRun())->toBeTrue();
});

test('installOnRun with explicit true enables auto-installation', function () {
    $this->packager->installOnRun(true);

    expect($this->packager->shouldInstallOnRun())->toBeTrue();
});

test('installOnRun with false disables auto-installation', function () {
    $this->packager->installOnRun(true)->installOnRun(false);

    expect($this->packager->shouldInstallOnRun())->toBeFalse();
});

// installOnRunInEnvironment()
test('installOnRunInEnvironment enables auto-install when env matches', function () {
    $this->packager->installOnRunInEnvironment('testing');

    expect($this->packager->shouldInstallOnRun())->toBeTrue();
});

test('installOnRunInEnvironment does not enable when env does not match', function () {
    $this->packager->installOnRunInEnvironment('production');

    expect($this->packager->shouldInstallOnRun())->toBeFalse();
});

test('installOnRunInEnvironment accepts array of environments and matches', function () {
    $this->packager->installOnRunInEnvironment(['local', 'testing']);

    expect($this->packager->shouldInstallOnRun())->toBeTrue();
});

test('installOnRunInEnvironment accepts array and does not match', function () {
    $this->packager->installOnRunInEnvironment(['local', 'production']);

    expect($this->packager->shouldInstallOnRun())->toBeFalse();
});

// installOnRunInLocal()
test('installOnRunInLocal does not enable auto-install in testing env', function () {
    $this->packager->installOnRunInLocal();

    expect($this->packager->shouldInstallOnRun())->toBeFalse();
});

// installOnRunInProduction()
test('installOnRunInProduction does not enable auto-install in testing env', function () {
    $this->packager->installOnRunInProduction();

    expect($this->packager->shouldInstallOnRun())->toBeFalse();
});

// getInstallCommandName()
test('getInstallCommandName returns default name based on short name', function () {
    expect($this->packager->getInstallCommandName())->toBe('test-package:install');
});

test('installCommandName sets custom suffix for command name', function () {
    $this->packager->installCommandName('setup');

    expect($this->packager->getInstallCommandName())->toBe('test-package:setup');
});

test('installCommandName preserves package short name prefix', function () {
    $this->packager->hasShortName('my-pkg')->installCommandName('configure');

    expect($this->packager->getInstallCommandName())->toBe('my-pkg:configure');
});

test('getInstallCommandName without custom name returns default install suffix', function () {
    $this->packager->hasShortName('acme-plugin');

    expect($this->packager->getInstallCommandName())->toBe('acme-plugin:install');
});

// createInstallCommand()
test('createInstallCommand returns InstallCommand instance', function () {
    $this->packager->hasInstallCommand();
    $command = $this->packager->createInstallCommand();

    expect($command)->toBeInstanceOf(InstallCommand::class);
});

test('createInstallCommand invokes callback', function () {
    $called = false;
    $this->packager->hasInstallCommand(function (InstallCommand $cmd) use (&$called) {
        $called = true;
    });

    $this->packager->createInstallCommand();

    expect($called)->toBeTrue();
});

test('createInstallCommand applies callback configuration', function () {
    $this->packager->hasInstallCommand(function (InstallCommand $cmd) {
        $cmd->publishConfig()->publishMigrations();
    });

    $command = $this->packager->createInstallCommand();

    expect($command->getPublishTags())
        ->toContain('config')
        ->toContain('migrations');
});

test('createInstallCommand without callback sets no tags', function () {
    $this->packager->hasInstallCommand();
    $command = $this->packager->createInstallCommand();

    expect($command->getPublishTags())->toBeEmpty();
});

// Quick install presets
test('hasQuickInstall is installable', function () {
    $this->packager->hasQuickInstall();

    expect($this->packager->isInstallable())->toBeTrue();
});

test('hasQuickInstall sets config migrations assets tags', function () {
    $this->packager->hasQuickInstall();
    $command = $this->packager->createInstallCommand();

    expect($command->getPublishTags())
        ->toContain('config')
        ->toContain('migrations')
        ->toContain('assets');
});

test('hasQuickInstall does not set views or routes tags', function () {
    $this->packager->hasQuickInstall();
    $command = $this->packager->createInstallCommand();

    expect($command->getPublishTags())
        ->not->toContain('views')
        ->not->toContain('routes');
});

test('hasFullInstall is installable', function () {
    $this->packager->hasFullInstall();

    expect($this->packager->isInstallable())->toBeTrue();
});

test('hasFullInstall sets all standard tags', function () {
    $this->packager->hasFullInstall();
    $command = $this->packager->createInstallCommand();

    expect($command->getPublishTags())
        ->toContain('config')
        ->toContain('migrations')
        ->toContain('routes')
        ->toContain('translations')
        ->toContain('assets')
        ->toContain('views')
        ->toContain('providers');
});

test('hasMinimalInstall is installable', function () {
    $this->packager->hasMinimalInstall();

    expect($this->packager->isInstallable())->toBeTrue();
});

test('hasMinimalInstall sets only config tag', function () {
    $this->packager->hasMinimalInstall();
    $command = $this->packager->createInstallCommand();

    expect($command->getPublishTags())
        ->toHaveCount(1)
        ->toContain('config');
});

test('hasDevInstall is installable', function () {
    $this->packager->hasDevInstall();

    expect($this->packager->isInstallable())->toBeTrue();
});

test('hasDevInstall sets config migrations views assets tags', function () {
    $this->packager->hasDevInstall();
    $command = $this->packager->createInstallCommand();

    expect($command->getPublishTags())
        ->toContain('config')
        ->toContain('migrations')
        ->toContain('views')
        ->toContain('assets');
});

test('hasDevInstall does not include routes in non-local env', function () {
    // In testing env, publishForLocal('routes') should not add routes
    $this->packager->hasDevInstall();
    $command = $this->packager->createInstallCommand();

    expect($command->getPublishTags())->not->toContain('routes');
});
