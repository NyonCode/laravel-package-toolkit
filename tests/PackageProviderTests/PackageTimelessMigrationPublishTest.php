<?php

namespace NyonCode\LaravelPackageToolkit\Tests\PackageProviderTests;

/**
 * Class PackageTimelessMigrationPublishTest
 *
 * @project   laravel-package-toolkit
 *
 * @author    Ondřej Nyklíček
 *
 * @created   20.03.2026
 */

use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\Tests\TestPackageData\src\TestServiceProvider;

trait PackageTimelessMigrationPublishTest
{
    public function configure(Packager $package): void
    {
        $package
            ->name('Package test')
            ->hasMigrations(directory: '../database/timeless_migrations');
    }
}

uses(PackageTimelessMigrationPublishTest::class);

test('timeless migrations are published with timestamp prefix', function () {
    $this->artisan('vendor:publish', ['--tag' => 'package-test::migrations'])
        ->assertExitCode(0);

    $publishedFiles = collect(File::files(database_path('migrations')))
        ->map(fn ($file) => $file->getFilename())
        ->filter(fn ($name) => str_contains($name, 'create_posts_table') || str_contains($name, 'create_comments_table'))
        ->values();

    expect($publishedFiles)->toHaveCount(2);

    $publishedFiles->each(function ($filename) {
        // Each published file should have a YYYY_MM_DD_HHMMSS_ prefix
        expect($filename)->toMatch('/^\d{4}_\d{2}_\d{2}_\d{6}_/');
    });

    // Clean up
    $publishedFiles->each(function ($filename) {
        $path = database_path('migrations/'.$filename);
        if (file_exists($path)) {
            unlink($path);
        }
    });
});

test('timeless migration publish mapping generates sequential timestamps', function () {
    $provider = app()->getProvider(TestServiceProvider::class);
    $reflection = new \ReflectionProperty($provider, 'packager');
    $reflection->setAccessible(true);

    /** @var Packager $packager */
    $packager = $reflection->getValue($provider);

    $mapping = $packager->getMigrationPublishMapping();

    expect($mapping)->toBeArray()
        ->toHaveCount(2);

    $destinations = array_values($mapping);
    $timestamps = [];

    foreach ($destinations as $dest) {
        $basename = basename($dest);
        preg_match('/^(\d{4}_\d{2}_\d{2}_\d{6})_/', $basename, $matches);
        expect($matches)->toHaveCount(2);
        $timestamps[] = $matches[1];
    }

    // Timestamps should be sequential (second >= first)
    expect($timestamps[1] >= $timestamps[0])->toBeTrue();
});

/**
 * The published migrations whose name ends in this one.
 *
 * @return array<int, string>
 */
function timelessPublished(string $name): array
{
    $found = collect(File::files(database_path('migrations')))
        ->map(fn ($file) => $file->getFilename())
        ->filter(fn ($filename) => str_contains($filename, $name))
        ->values()
        ->all();

    sort($found);

    return $found;
}

/** Reach the packager the provider under test was configured with. */
function timelessPackager(): Packager
{
    $reflection = new \ReflectionProperty(app()->getProvider(TestServiceProvider::class), 'packager');
    $reflection->setAccessible(true);

    /** @var Packager $packager */
    $packager = $reflection->getValue(app()->getProvider(TestServiceProvider::class));

    return $packager;
}

test('publishing a second time overwrites the copy, rather than adding another', function () {
    // A timeless migration's destination name carries the clock of the process
    // that built the mapping. A later run used to look for a file that could not
    // be there, find nothing, and write a second copy — so `migrate` met
    // `create_posts_table` twice and failed on the second.
    $this->artisan('vendor:publish', ['--tag' => 'package-test::migrations'])->assertExitCode(0);

    $first = timelessPublished('create_posts_table');

    expect($first)->toHaveCount(1);

    try {
        // A day later, and that matters: the stamp has second resolution, so two
        // publishes in the same second land on one filename and overwrite each
        // other whatever the mapping says. Without moving the clock this test
        // passes against the bug it is here to catch.
        Carbon::setTestNow(Carbon::now()->addDay());

        // What the next process does: registers publishing again, over a
        // mapping built from scratch against a database/migrations that now has
        // the earlier run's file in it.
        $this->resetServiceProviderState();
        app()->getProvider(TestServiceProvider::class)->registerPublishing();

        $this->artisan('vendor:publish', [
            '--tag' => 'package-test::migrations',
            '--force' => true,
        ])->assertExitCode(0);

        expect(timelessPublished('create_posts_table'))->toBe($first)
            ->and(timelessPublished('create_comments_table'))->toHaveCount(1);
    } finally {
        Carbon::setTestNow();

        foreach (timelessPublished('create_posts_table') as $filename) {
            File::delete(database_path('migrations/'.$filename));
        }

        foreach (timelessPublished('create_comments_table') as $filename) {
            File::delete(database_path('migrations/'.$filename));
        }
    }
});

test('the mapping points at the copy an earlier run published, under its own stamp', function () {
    File::ensureDirectoryExists(database_path('migrations'));

    $earliest = database_path('migrations/2024_01_01_000000_create_posts_table.php');
    $later = database_path('migrations/2025_02_02_000000_create_posts_table.php');

    File::put($earliest, '<?php // published by an earlier run');
    // Two copies is the state this fix exists to stop producing, and an
    // application upgrading into it still has to land somewhere: the earliest,
    // because that is the one `migrate` has already run.
    File::put($later, '<?php // and the duplicate it used to make');

    try {
        $destination = collect(timelessPackager()->getMigrationPublishMapping())
            ->first(fn ($path) => str_contains($path, 'create_posts_table'));

        expect($destination)->toBe($earliest);
    } finally {
        File::delete([$earliest, $later]);
    }
});

test('a migration nothing has published still gets a fresh stamp', function () {
    // The other half: reuse must not turn into "never publish".
    $destination = collect(timelessPackager()->getMigrationPublishMapping())
        ->first(fn ($path) => str_contains($path, 'create_comments_table'));

    expect(basename($destination))->toMatch('/^\d{4}_\d{2}_\d{2}_\d{6}_create_comments_table\.php$/');
});

test('publishing again without --force leaves the file, and any edit to it, alone', function () {
    // The safety property the name match rests on. Matching an existing
    // publication by name is what stops a second copy appearing — but it also
    // means the destination is now a file somebody may have edited. Without
    // `--force`, Laravel skips a destination that exists, so nothing is lost;
    // with it, rewriting is the whole point of the flag.
    $this->artisan('vendor:publish', ['--tag' => 'package-test::migrations'])->assertExitCode(0);

    $published = timelessPublished('create_posts_table');

    expect($published)->toHaveCount(1);

    $path = database_path('migrations/'.$published[0]);

    try {
        File::put($path, '<?php // edited after publishing');

        Carbon::setTestNow(Carbon::now()->addDay());
        $this->resetServiceProviderState();
        app()->getProvider(TestServiceProvider::class)->registerPublishing();

        $this->artisan('vendor:publish', ['--tag' => 'package-test::migrations'])->assertExitCode(0);

        expect(timelessPublished('create_posts_table'))->toBe($published)
            ->and(File::get($path))->toBe('<?php // edited after publishing');
    } finally {
        Carbon::setTestNow();

        foreach (['create_posts_table', 'create_comments_table'] as $name) {
            foreach (timelessPublished($name) as $filename) {
                File::delete(database_path('migrations/'.$filename));
            }
        }
    }
});
