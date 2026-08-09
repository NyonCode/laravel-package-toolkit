<?php

namespace NyonCode\LaravelPackageToolkit\Tests\Unit;

use NyonCode\LaravelPackageToolkit\Commands\InstallCommand;
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $packager = new Packager();
    $packager->name('Test Package');
    $this->command = new InstallCommand($packager);
});

// Default state
test('getPublishTags returns empty array by default', function () {
    expect($this->command->getPublishTags())->toBeArray()->toBeEmpty();
});

test('willPublish returns false for any tag by default', function () {
    expect($this->command->willPublish('config'))->toBeFalse();
    expect($this->command->willPublish('migrations'))->toBeFalse();
});

// Individual publish methods
test('publishConfig adds config tag', function () {
    $this->command->publishConfig();

    expect($this->command->getPublishTags())->toContain('config');
    expect($this->command->willPublish('config'))->toBeTrue();
});

test('publishConfigFile is alias for publishConfig', function () {
    $this->command->publishConfigFile();

    expect($this->command->getPublishTags())->toContain('config');
});

test('publishConfigFiles is alias for publishConfig', function () {
    $this->command->publishConfigFiles();

    expect($this->command->getPublishTags())->toContain('config');
});

test('publishMigrations adds migrations tag', function () {
    $this->command->publishMigrations();

    expect($this->command->getPublishTags())->toContain('migrations');
    expect($this->command->willPublish('migrations'))->toBeTrue();
});

test('publishSeeders adds seeders tag', function () {
    $this->command->publishSeeders();

    expect($this->command->getPublishTags())->toContain('seeders');
    expect($this->command->willPublish('seeders'))->toBeTrue();
});

test('publishFactories adds factories tag', function () {
    $this->command->publishFactories();

    expect($this->command->getPublishTags())->toContain('factories');
    expect($this->command->willPublish('factories'))->toBeTrue();
});

test('publishStubs adds stubs tag', function () {
    $this->command->publishStubs();

    expect($this->command->getPublishTags())->toContain('stubs');
    expect($this->command->willPublish('stubs'))->toBeTrue();
});

test('publishRoutes adds routes tag', function () {
    $this->command->publishRoutes();

    expect($this->command->getPublishTags())->toContain('routes');
    expect($this->command->willPublish('routes'))->toBeTrue();
});

test('publishRouteFiles is alias for publishRoutes', function () {
    $this->command->publishRouteFiles();

    expect($this->command->getPublishTags())->toContain('routes');
});

test('publishTranslations adds translations tag', function () {
    $this->command->publishTranslations();

    expect($this->command->getPublishTags())->toContain('translations');
    expect($this->command->willPublish('translations'))->toBeTrue();
});

test('publishTranslationFiles is alias for publishTranslations', function () {
    $this->command->publishTranslationFiles();

    expect($this->command->getPublishTags())->toContain('translations');
});

test('publishLanguageFiles is alias for publishTranslations', function () {
    $this->command->publishLanguageFiles();

    expect($this->command->getPublishTags())->toContain('translations');
});

test('publishAssets adds assets tag', function () {
    $this->command->publishAssets();

    expect($this->command->getPublishTags())->toContain('assets');
    expect($this->command->willPublish('assets'))->toBeTrue();
});

test('publishPublicAssets is alias for publishAssets', function () {
    $this->command->publishPublicAssets();

    expect($this->command->getPublishTags())->toContain('assets');
});

test('publishViews adds views tag', function () {
    $this->command->publishViews();

    expect($this->command->getPublishTags())->toContain('views');
    expect($this->command->willPublish('views'))->toBeTrue();
});

test('publishViewFiles is alias for publishViews', function () {
    $this->command->publishViewFiles();

    expect($this->command->getPublishTags())->toContain('views');
});

test('publishProviders adds providers tag', function () {
    $this->command->publishProviders();

    expect($this->command->getPublishTags())->toContain('providers');
    expect($this->command->willPublish('providers'))->toBeTrue();
});

test('publishServiceProviders is alias for publishProviders', function () {
    $this->command->publishServiceProviders();

    expect($this->command->getPublishTags())->toContain('providers');
});

test('publishComponents adds view-components tag', function () {
    $this->command->publishComponents();

    expect($this->command->getPublishTags())->toContain('view-components');
    expect($this->command->willPublish('view-components'))->toBeTrue();
});

test('publishViewComponents is alias for publishComponents', function () {
    $this->command->publishViewComponents();

    expect($this->command->getPublishTags())->toContain('view-components');
});

test('publishComponentNamespaces adds view-component-namespaces tag', function () {
    $this->command->publishComponentNamespaces();

    expect($this->command->getPublishTags())->toContain('view-component-namespaces');
    expect($this->command->willPublish('view-component-namespaces'))->toBeTrue();
});

test('publishViewComponentNamespaces is alias for publishComponentNamespaces', function () {
    $this->command->publishViewComponentNamespaces();

    expect($this->command->getPublishTags())->toContain('view-component-namespaces');
});

// Batch methods
test('publishEverything adds all standard tags', function () {
    $this->command->publishEverything();
    $tags = $this->command->getPublishTags();

    expect($tags)
        ->toContain('config')
        ->toContain('migrations')
        ->toContain('seeders')
        ->toContain('factories')
        ->toContain('routes')
        ->toContain('translations')
        ->toContain('assets')
        ->toContain('views')
        ->toContain('providers')
        ->toContain('stubs')
        ->toContain('view-components')
        ->toContain('view-component-namespaces');
});

test('publishAll is alias for publishEverything', function () {
    $this->command->publishAll();
    $tags = $this->command->getPublishTags();

    expect($tags)
        ->toContain('config')
        ->toContain('migrations')
        ->toContain('routes')
        ->toContain('assets')
        ->toContain('views');
});

test('publishEssentials adds config migrations assets', function () {
    $this->command->publishEssentials();
    $tags = $this->command->getPublishTags();

    expect($tags)
        ->toContain('config')
        ->toContain('migrations')
        ->toContain('assets')
        ->not->toContain('routes')
        ->not->toContain('views')
        ->not->toContain('translations');
});

// Tag management
test('clearPublishTags empties all tags', function () {
    $this->command->publishConfig()->publishMigrations()->publishViews();
    $this->command->clearPublishTags();

    expect($this->command->getPublishTags())->toBeEmpty();
});

test('clearPublishTags makes willPublish return false', function () {
    $this->command->publishConfig();
    $this->command->clearPublishTags();

    expect($this->command->willPublish('config'))->toBeFalse();
});

test('willPublish returns true only for added tags', function () {
    $this->command->publishConfig()->publishMigrations();

    expect($this->command->willPublish('config'))->toBeTrue();
    expect($this->command->willPublish('migrations'))->toBeTrue();
    expect($this->command->willPublish('routes'))->toBeFalse();
    expect($this->command->willPublish('views'))->toBeFalse();
});

// Fluent interface
test('publish methods return same instance for chaining', function () {
    $result = $this->command->publishConfig()->publishMigrations()->publishViews();

    expect($result)->toBe($this->command);
});

test('multiple publish calls accumulate tags', function () {
    $this->command->publishConfig()->publishMigrations()->publishAssets();

    expect($this->command->getPublishTags())
        ->toHaveCount(3)
        ->toContain('config')
        ->toContain('migrations')
        ->toContain('assets');
});

// Conditional publishing — publishIf
test('publishIf adds tag when condition is true', function () {
    $this->command->publishIf(true, 'config');

    expect($this->command->getPublishTags())->toContain('config');
});

test('publishIf does not add tag when condition is false', function () {
    $this->command->publishIf(false, 'config');

    expect($this->command->getPublishTags())->not->toContain('config');
});

test('publishIf supports multiple tags', function () {
    $this->command->publishIf(true, 'config', 'migrations');

    expect($this->command->getPublishTags())
        ->toContain('config')
        ->toContain('migrations');
});

test('publishIf returns same instance for chaining', function () {
    $result = $this->command->publishIf(true, 'config');

    expect($result)->toBe($this->command);
});

// publishUnless
test('publishUnless adds tag when condition is false', function () {
    $this->command->publishUnless(false, 'migrations');

    expect($this->command->getPublishTags())->toContain('migrations');
});

test('publishUnless does not add tag when condition is true', function () {
    $this->command->publishUnless(true, 'migrations');

    expect($this->command->getPublishTags())->not->toContain('migrations');
});

test('publishUnless is inverse of publishIf', function () {
    $this->command->publishIf(true, 'config');
    $this->command->publishUnless(true, 'migrations');
    $this->command->publishIf(false, 'routes');
    $this->command->publishUnless(false, 'views');

    expect($this->command->getPublishTags())
        ->toContain('config')
        ->not->toContain('migrations')
        ->not->toContain('routes')
        ->toContain('views');
});

// publishForEnvironment
test('publishForEnvironment adds tag when current env matches string', function () {
    $this->command->publishForEnvironment('testing', 'config');

    expect($this->command->getPublishTags())->toContain('config');
});

test('publishForEnvironment does not add tag when env does not match', function () {
    $this->command->publishForEnvironment('production', 'config');

    expect($this->command->getPublishTags())->not->toContain('config');
});

test('publishForEnvironment accepts array of environments', function () {
    $this->command->publishForEnvironment(['staging', 'testing'], 'config');

    expect($this->command->getPublishTags())->toContain('config');
});

test('publishForEnvironment with array does not match when none match', function () {
    $this->command->publishForEnvironment(['local', 'production'], 'config');

    expect($this->command->getPublishTags())->not->toContain('config');
});

test('publishForEnvironment supports multiple tags', function () {
    $this->command->publishForEnvironment('testing', 'config', 'migrations');

    expect($this->command->getPublishTags())
        ->toContain('config')
        ->toContain('migrations');
});

// publishForLocal
test('publishForLocal does not add tag in testing env', function () {
    $this->command->publishForLocal('routes');

    expect($this->command->getPublishTags())->not->toContain('routes');
});

// publishForProduction
test('publishForProduction does not add tag in testing env', function () {
    $this->command->publishForProduction('config');

    expect($this->command->getPublishTags())->not->toContain('config');
});

// publishCustom
test('publishCustom adds custom tag', function () {
    $this->command->publishCustom('my-custom-tag');

    expect($this->command->getPublishTags())->toContain('my-custom-tag');
});

test('publishCustom adds multiple custom tags', function () {
    $this->command->publishCustom('tag-a', 'tag-b', 'tag-c');

    expect($this->command->getPublishTags())
        ->toContain('tag-a')
        ->toContain('tag-b')
        ->toContain('tag-c');
});

test('publishCustom returns same instance for chaining', function () {
    $result = $this->command->publishCustom('my-tag');

    expect($result)->toBe($this->command);
});
