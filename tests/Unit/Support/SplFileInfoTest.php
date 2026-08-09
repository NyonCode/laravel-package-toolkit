<?php

use NyonCode\LaravelPackageToolkit\Support\SplFileInfo;

test('the base file name drops the extension', function () {
    $file = new SplFileInfo(__DIR__.'/../../TestPackageData/config/test-config.php');

    expect($file->getBaseFileName())->toBe('test-config')
        ->and($file->getExtension())->toBe('php');
});

test('a file without an extension keeps its whole name', function () {
    $file = new SplFileInfo(__DIR__.'/../../TestPackageData');

    expect($file->getBaseFileName())->toBe('TestPackageData');
});

test('the file size is reported in bytes', function () {
    $path = __DIR__.'/../../TestPackageData/config/test-config.php';
    $file = new SplFileInfo($path);

    expect($file->getFileSize())->toBe(filesize($path))
        ->and($file->getFileSize())->toBeGreaterThan(0);
});
