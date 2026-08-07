<?php

declare(strict_types=1);

namespace NyonCode\LaravelPackageToolkit\Support\Concerns;

use NyonCode\LaravelPackageToolkit\Support\PublishedAssets;

/**
 * The provider half of the asset mirror: it declares *where* the package ships its
 * assets, so {@see PublishedAssets} can keep `public/vendor/{short-name}` in step
 * with them.
 *
 * The split is deliberate. The provider is the only place that knows the directory
 * for certain — it is where `hasAssets()` named it — so it hands that to the resolver
 * rather than letting it infer one from an asset path. What it does *not* do is the
 * copying: that stays lazy, behind the first asset of the package to resolve a URL in
 * a request. Mirroring from `register()` or `boot()` would put a directory walk on
 * every request the application serves, including the queue worker, the API route and
 * the artisan command that will never emit a `<script>`.
 *
 * Everything is read off the packager, so the publish tags
 * ({@see PublishesPackageResources::publishAssets()}) and the mirror cannot drift
 * onto different directories, and `isAssetable()` means a package that never called
 * `hasAssets()` declares nothing. `hasAssets(mirror: false)` opts a package out while
 * keeping the publish tags.
 */
trait MirrorsPackageAssets
{
    /**
     * Register the package's asset directory with the shared mirror.
     */
    protected function registerAssetMirror(): void
    {
        if (! ($this->packager?->isAssetable() ?? false) || ! $this->packager->mirrorsAssets()) {
            return;
        }

        // `singletonIf`, not `singleton` — the resolver is shared by every package in
        // the application, so the fourth provider to register must not wipe the three
        // directories already declared.
        $this->app->singletonIf(PublishedAssets::class);

        $this->app->make(PublishedAssets::class)->mirrors(
            $this->packager->shortName(),
            $this->packager->assetDirectory(),
        );
    }
}
