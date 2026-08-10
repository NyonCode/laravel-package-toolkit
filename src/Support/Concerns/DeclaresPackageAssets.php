<?php

declare(strict_types=1);

namespace NyonCode\LaravelPackageToolkit\Support\Concerns;

use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Support\Facades\Blade;
use NyonCode\LaravelPackageToolkit\Support\Asset;
use NyonCode\LaravelPackageToolkit\Support\PackageAssets;

/**
 * The provider half of the template-side asset declaration: it hands the packager's
 * declared entries to {@see PackageAssets} and registers the Blade directives that
 * render them.
 *
 * Writing the tags was the part the toolkit left to the package author, and it was never
 * one line: a helper class holding the paths, a `styleTag()` that escapes and assembles
 * the markup, a `Blade::directive()` registered from a lifecycle hook, and — the moment
 * the consuming application wanted the package inside its own Vite build — a second path
 * through the whole thing. All of it is boilerplate derived from facts the packager
 * already has, which is exactly what this toolkit exists to remove.
 *
 * Directives are global rather than per package, and take the short name — a template
 * writes `packageStyles('blog')` and `packageScripts('blog')`, each behind Blade's `@`.
 *
 * A generated `blogStyles` would read marginally better in the package's own layout and
 * pay for it everywhere else — a name that exists only if that package is installed,
 * cannot be grepped for, and collides silently with another package's. The short name is
 * already the namespace of the package's views, translations and publish tags, so the
 * template says which package it means the same way the rest of the toolkit does.
 */
trait DeclaresPackageAssets
{
    /**
     * Hand the package's declared entries to the shared renderer.
     *
     * Registration is bookkeeping: no manifest is read and no file is touched until a
     * template actually renders a tag.
     */
    protected function registerPackageAssets(): void
    {
        if (! ($this->packager?->hasAssetEntries() ?? false)) {
            return;
        }

        // `singletonIf`, not `singleton` — the renderer is shared by every package in the
        // application, so the fourth provider to register must not wipe the three
        // declarations already made.
        $this->app->singletonIf(PackageAssets::class);

        $this->app->make(PackageAssets::class)->declare(
            package: $this->packager->shortName(),
            directory: $this->packager->assetDirectory(),
            entries: $this->packager->assetEntries(),
            base: $this->packager->viteBase() ?? $this->derivePackageBase(),
            mirrored: $this->packager->isAssetable() && $this->packager->mirrorsAssets(),
            fallback: $this->packager->assetFallback(),
        );
    }

    /**
     * Register the Blade directives, once per application.
     *
     * Guarded on the compiler rather than on a static flag so the registration follows
     * the container's lifetime: a test that rebuilds the application gets a fresh
     * compiler and registers again, and a second package booting into the same one does
     * not re-register.
     *
     * Not guarded on anything being declared, though. Blade leaves a directive it does not
     * know as text, so a layout carrying `@packageStyles('blog')` while nothing declares an
     * entry used to print that line into the page — the raw directive, visible in the
     * browser. It is the shape a package reaches by ordinary means: an asset directory of
     * fonts and images has nothing to tag, a `hasAssets()` behind a conditional is not
     * reached in production, and an application writes the line in its layout before
     * installing the package that answers it. Registering regardless costs one closure and
     * renders nothing, which is what all three of those wanted.
     */
    public function bootAssets(): static
    {
        if (! $this->app->bound('blade.compiler')) {
            return $this;
        }

        // `packageAssetUrl`, not `packageAsset`: one character from `packageAssets` and
        // returning a bare URL instead of tags is a typo that renders something plausible
        // in the wrong place — or, since `url()` needs both arguments, an
        // `ArgumentCountError` out of a template.
        $directives = [
            'packageAssets' => 'tags',
            'packageStyles' => 'styles',
            'packageScripts' => 'scripts',
            'packageAssetUrl' => 'url',
        ];

        $registered = Blade::getCustomDirectives();

        foreach ($directives as $directive => $method) {
            if (isset($registered[$directive])) {
                continue;
            }

            Blade::directive(
                $directive,
                fn (string $expression): string => '<?php echo app(\\'.PackageAssets::class."::class)->$method($expression); ?>",
            );
        }

        $this->reportAssetResolution();

        return $this;
    }

    /**
     * Add a line to the package's `about` section naming how each entry resolves.
     *
     * Only for a package that declared Vite sources, because that is the only case with
     * something to get wrong quietly: an application listing the package's input under a
     * path one segment off from the manifest key leaves every page working, served from
     * the shipped file, with nothing anywhere saying the build it configured is not being
     * used. `php artisan about` is where a developer already looks to find out what a
     * package thinks is true.
     *
     * Only for a package that called `hasAbout()`, too — `AboutCommand::add()` would
     * happily conjure a section for a package that asked for none, and a diagnostic is
     * not a good reason to override that choice.
     */
    private function reportAssetResolution(): void
    {
        if (! ($this->packager?->hasAssetEntries() ?? false)) {
            return;
        }

        if (! $this->packager->isAboutable() || ! $this->app->runningInConsole()) {
            return;
        }

        $hasViteSources = array_filter(
            $this->packager->assetEntries(),
            fn (Asset $asset): bool => $asset->source() !== null,
        );

        if ($hasViteSources === []) {
            return;
        }

        $package = $this->packager->shortName();

        AboutCommand::add($this->packager->name, [
            'Assets' => function () use ($package): string {
                $resolution = $this->app->make(PackageAssets::class)->resolution($package);

                $lines = [];

                foreach ($resolution as $entry => $how) {
                    $lines[] = "$entry: $how";
                }

                return implode(', ', $lines);
            },
        ]);
    }

    /**
     * Where the package sits under the application, which is the prefix the application's
     * `vite.config.js` writes in front of every source path it lists as an input —
     * `vendor/acme/blog` for an installed package.
     *
     * Derived rather than declared because the answer is the same for every package
     * installed the normal way, and getting it wrong is invisible: the manifest lookup
     * simply misses and the shipped file is served, which looks exactly like an
     * application that chose not to build the package. `null` for a package outside the
     * application root — a path repository, a symlinked local checkout — where nothing
     * can be inferred and `hasViteAssets(base: …)` has to say.
     */
    private function derivePackageBase(): ?string
    {
        $root = rtrim(str_replace('\\', '/', base_path()), '/');
        $package = rtrim(str_replace('\\', '/', dirname($this->packager->basePath())), '/');

        if ($package === $root) {
            return '';
        }

        return str_starts_with($package, $root.'/')
            ? substr($package, strlen($root) + 1)
            : null;
    }
}
