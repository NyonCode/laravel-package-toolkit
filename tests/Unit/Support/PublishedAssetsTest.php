<?php

namespace NyonCode\LaravelPackageToolkit\Tests\Unit\Support;

use Illuminate\Support\Facades\File;
use NyonCode\LaravelPackageToolkit\Support\PublishedAssets;
use NyonCode\LaravelPackageToolkit\Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->package = 'mirror-test';
    $this->shipped = sys_get_temp_dir().'/lpt-mirror-'.getmypid();

    File::ensureDirectoryExists($this->shipped.'/dist/js');
    File::put($this->shipped.'/dist/js/app.js', 'console.log(1)');
    File::put($this->shipped.'/dist/js/chunk.js', 'export default 1');

    $this->assets = new PublishedAssets();
});

afterEach(function () {
    File::deleteDirectory($this->shipped);
    File::deleteDirectory(public_path('vendor/'.$this->package));
});

test('a declared directory is mirrored whole, not just the file asked about', function () {
    $this->assets->mirrors($this->package, $this->shipped.'/dist');

    $url = $this->assets->url($this->package, $this->shipped.'/dist/js/app.js');

    expect($url)->toContain('vendor/'.$this->package.'/js/app.js?id=')
        ->and(public_path('vendor/'.$this->package.'/js/chunk.js'))->toBeFile();
});

test('the resolved url is memoized per asset', function () {
    $this->assets->mirrors($this->package, $this->shipped.'/dist');

    $first = $this->assets->url($this->package, $this->shipped.'/dist/js/app.js');

    File::deleteDirectory(public_path('vendor/'.$this->package));

    expect($this->assets->url($this->package, $this->shipped.'/dist/js/app.js'))->toBe($first);
});

test('an undeclared package has its root read back off a dist path', function () {
    $url = $this->assets->url($this->package, $this->shipped.'/dist/js/app.js');

    expect($url)->toContain('vendor/'.$this->package.'/js/app.js?id=');
});

test('an undeclared package outside a dist directory publishes nothing', function () {
    File::ensureDirectoryExists($this->shipped.'/build');
    File::put($this->shipped.'/build/app.js', 'console.log(1)');

    expect($this->assets->url($this->package, $this->shipped.'/build/app.js'))->toBeNull()
        ->and(File::exists(public_path('vendor/'.$this->package)))->toBeFalse();
});

test('a declared directory that does not exist publishes nothing', function () {
    $this->assets->mirrors($this->package, $this->shipped.'/missing');

    expect($this->assets->url($this->package, $this->shipped.'/missing/app.js'))->toBeNull();
});

test('an up-to-date copy is left alone', function () {
    $this->assets->mirrors($this->package, $this->shipped.'/dist');
    $this->assets->url($this->package, $this->shipped.'/dist/js/app.js');

    $published = public_path('vendor/'.$this->package.'/js/app.js');
    $mtime = filemtime($published);

    $this->assets->flush();
    $this->assets->url($this->package, $this->shipped.'/dist/js/app.js');

    expect(filemtime($published))->toBe($mtime);
});

test('a shipped file newer than its published copy is copied again', function () {
    $this->assets->mirrors($this->package, $this->shipped.'/dist');
    $this->assets->url($this->package, $this->shipped.'/dist/js/app.js');

    $published = public_path('vendor/'.$this->package.'/js/app.js');
    touch($published, time() - 60);
    clearstatcache(true, $published);

    $this->assets->flush();
    $this->assets->url($this->package, $this->shipped.'/dist/js/app.js');

    expect(File::get($published))->toBe('console.log(1)')
        ->and(filemtime($published))->toBeGreaterThan(time() - 60);
});

// isStale()
test('a published copy older than the shipped file is stale', function () {
    $this->assets->mirrors($this->package, $this->shipped.'/dist');
    $this->assets->url($this->package, $this->shipped.'/dist/js/app.js');

    // Before PHP 8.3, touch() left the mtime it changed in the stat cache, and the
    // copy above already primed it — so the age set here is only visible once cleared.
    $published = public_path('vendor/'.$this->package.'/js/app.js');
    touch($published, time() - 60);
    clearstatcache(true, $published);

    expect($this->assets->isStale($this->package, $this->shipped.'/dist/js/app.js'))->toBeTrue();
});

test('a current copy is not stale', function () {
    $this->assets->mirrors($this->package, $this->shipped.'/dist');
    $this->assets->url($this->package, $this->shipped.'/dist/js/app.js');

    expect($this->assets->isStale($this->package, $this->shipped.'/dist/js/app.js'))->toBeFalse();
});

test('an unpublished asset is not stale', function () {
    expect($this->assets->isStale($this->package, $this->shipped.'/dist/js/app.js'))->toBeFalse();
});

// A public/ that cannot be written
test('a read-only public directory yields no url and no exception', function () {
    $readOnly = sys_get_temp_dir().'/lpt-readonly-'.getmypid();
    File::ensureDirectoryExists($readOnly);
    chmod($readOnly, 0o555);

    $this->app->usePublicPath($readOnly);
    $this->assets->mirrors($this->package, $this->shipped.'/dist');

    try {
        expect($this->assets->url($this->package, $this->shipped.'/dist/js/app.js'))->toBeNull();
    } finally {
        chmod($readOnly, 0o755);
        File::deleteDirectory($readOnly);
    }
})->skip(fn () => ! permissionsAreEnforced(), 'Permission bits are not enforced here.');
