<?php

namespace NyonCode\LaravelPackageToolkit\Tests\Unit;

use Illuminate\Support\Facades\File;
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\Tests\TestCase;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;

uses(TestCase::class);

/**
 * Discovery is meant to survive a file it cannot read rather than take the whole
 * package down with it. Root ignores the permission bits, so these are skipped there.
 */
beforeEach(function () {
    $this->root = sys_get_temp_dir().'/lpt-unreadable-'.getmypid();
    File::ensureDirectoryExists($this->root.'/files');

    $this->packager = (new Packager())->name('Test Package');
    $this->packager->hasBasePath($this->root);
});

afterEach(function () {
    @chmod($this->root.'/files', 0o755);
    @chmod($this->root.'/files/secret.php', 0o644);

    File::deleteDirectory($this->root);
});

test('an unreadable file is skipped rather than failing the discovery', function () {
    File::put($this->root.'/files/visible.php', '<?php return [];');
    File::put($this->root.'/files/secret.php', '<?php return [];');
    chmod($this->root.'/files/secret.php', 0o000);

    $files = $this->packager->resolveFiles(null, 'files');

    expect($files)->toHaveCount(1)
        ->and($files[0]->getBaseFileName())->toBe('visible');
})->skip(fn () => is_readable('/etc/sudoers'), 'Running as root: permission bits are ignored.');

test('an unreadable directory is reported', function () {
    File::put($this->root.'/files/visible.php', '<?php return [];');
    chmod($this->root.'/files', 0o000);

    expect(fn () => $this->packager->resolveFiles(null, 'files'))
        ->toThrow(DirectoryNotFoundException::class, 'does not exist or is not readable');
})->skip(fn () => is_readable('/etc/sudoers'), 'Running as root: permission bits are ignored.');
