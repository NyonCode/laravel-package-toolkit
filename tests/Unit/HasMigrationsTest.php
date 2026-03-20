<?php

namespace NyonCode\LaravelPackageToolkit\Tests\Unit;

/**
 * Class HasMigrationsTest
 *
 * @project   laravel-package-toolkit
 *
 * @author    Ondřej Nyklíček
 *
 * @created   20.03.2026
 */

use NyonCode\LaravelPackageToolkit\Packager;

beforeEach(function () {
    $this->packager = new Packager();
    $this->packager->name('Test Package');
});

test('hasDatePrefix returns true for standard Laravel migration filenames', closure: function (string $filename) {
    expect($this->packager->hasDatePrefix($filename))->toBeTrue();
})->with([
    '2025_01_01_000000_create_users_table.php',
    '2024_12_31_235959_add_column_to_posts.php',
    '2023_06_15_143022_create_orders_table.php',
    '1999_01_01_000000_legacy_migration.php',
]);

test('hasDatePrefix returns false for filenames without date prefix', function (string $filename) {
    expect($this->packager->hasDatePrefix($filename))->toBeFalse();
})->with([
    'create_users_table.php',
    'add_column_to_posts.php',
    '2025_create_table.php',
    '2025_01_create_table.php',
    '2025_01_01_create_table.php',
    'create_posts_table.php',
]);

test('shouldPrependTimestamp is false by default', function () {
    expect($this->packager->shouldPrependTimestamp())->toBeFalse();
});
