<?php

declare(strict_types=1);

namespace NyonCode\LaravelPackageToolkit\Support;

use InvalidArgumentException;

/**
 * One declared asset: what the application's Vite build compiles, what the package
 * ships as a built file, or both.
 *
 * The two halves answer different questions and a package usually wants both. The
 * `source` is a path *inside the package* that the consuming application can list as a
 * Vite input — `vendor/acme/blog/resources/js/blog.js` — so its own build handles the
 * package the same way it handles its own code: one manifest, one hashed filename, one
 * Tailwind pass over the package's Blade views. The `file` is the built copy the package
 * ships in `dist/`, which is what gets served when the application never added that
 * input, which is the normal case for a package installed and used as-is.
 *
 * Both are declared together and resolved in that order at render time, per entry, by
 * {@see PackageAssets}. Nothing here reads the filesystem: an `Asset` is a declaration,
 * and the eager validation that the paths exist happens where they are declared, in
 * `Concerns\HasAssets`.
 *
 * A plain string is accepted anywhere an `Asset` is — `'css/blog.css'` means
 * {@see self::make()}, and this class is only reached for what a string cannot express:
 * a classic (non-module) script, or extra HTML attributes.
 */
final class Asset
{
    /**
     * @var array<string, string|bool|null> extra attributes for the rendered tag
     */
    private array $attributes = [];

    /**
     * @var bool|null explicit stylesheet/script override; `null` infers from the extension
     */
    private ?bool $stylesheet = null;

    /**
     * @var bool whether a shipped script is emitted as `type="module"`
     */
    private bool $module = true;

    /**
     * @param  string|null  $source  path inside the package that the application's Vite build compiles
     * @param  string|null  $file  path inside the package's asset directory of the shipped, built file
     */
    private function __construct(
        private ?string $source = null,
        private ?string $file = null,
    ) {}

    /**
     * A file the package ships already built, relative to its asset directory.
     *
     * @param  string  $file  e.g. `css/blog.css`
     */
    public static function make(string $file): self
    {
        return new self(file: self::clean($file, 'file'));
    }

    /**
     * A source file the consuming application's Vite build can compile, relative to the
     * package root.
     *
     * The path doubles as the manifest key the application's `vite.config.js` lists as
     * an input, once prefixed with the package's location under the application —
     * `vendor/acme/blog/` for an installed package.
     *
     * @param  string  $source  e.g. `resources/js/blog.js`
     */
    public static function vite(string $source): self
    {
        return new self(source: self::clean($source, 'source'));
    }

    /**
     * The shipped file to serve when the application's build does not cover this entry.
     *
     * @param  string  $file  relative to the package's asset directory, e.g. `js/blog.js`
     */
    public function fallback(string $file): self
    {
        $this->file = self::clean($file, 'file');

        return $this;
    }

    /**
     * Emit the shipped script as a classic script rather than `type="module"`.
     *
     * The default is a module because that is what a Vite build produces. Reach for this
     * when the package ships an IIFE or UMD bundle: a module is deferred and its
     * top-level declarations never reach `window`, so a bundle that expects to export a
     * global silently stops working under the default.
     */
    public function classic(): self
    {
        $this->module = false;

        return $this;
    }

    /**
     * Extra attributes for the rendered tag.
     *
     * A `true` value renders the attribute bare (`defer`), `false` and `null` drop it —
     * which is also how a default is removed, e.g. `['data-navigate-track' => null]`.
     *
     * @param  array<string, string|bool|null>  $attributes
     */
    public function attributes(array $attributes): self
    {
        $this->attributes = array_merge($this->attributes, $attributes);

        return $this;
    }

    /**
     * Render as a stylesheet, whatever the extension says.
     */
    public function asStylesheet(): self
    {
        $this->stylesheet = true;

        return $this;
    }

    /**
     * Render as a script, whatever the extension says.
     */
    public function asScript(): self
    {
        $this->stylesheet = false;

        return $this;
    }

    /**
     * The Vite input path, or `null` for a shipped-only asset.
     */
    public function source(): ?string
    {
        return $this->source;
    }

    /**
     * The shipped file, or `null` for an asset only the application's build produces.
     */
    public function file(): ?string
    {
        return $this->file;
    }

    /**
     * What identifies this entry to `@packageAssets('blog', '…')` — the shipped file
     * when there is one, otherwise the Vite source.
     */
    public function key(): string
    {
        return $this->file ?? $this->source ?? '';
    }

    /**
     * Whether this entry renders as a `<link rel="stylesheet">`.
     *
     * Inferred from the extension of whichever path is declared, because the two always
     * agree in practice — a `.scss` source builds to a `.css` file. An explicit
     * {@see self::asStylesheet()} wins, for the build that does not.
     */
    public function isStylesheet(): bool
    {
        if ($this->stylesheet !== null) {
            return $this->stylesheet;
        }

        $extension = strtolower(pathinfo($this->key(), PATHINFO_EXTENSION));

        return in_array($extension, ['css', 'scss', 'sass', 'less', 'styl', 'pcss'], true);
    }

    /**
     * Whether a shipped script renders as `type="module"`.
     */
    public function isModule(): bool
    {
        return $this->module;
    }

    /**
     * The declared attributes, merged over the defaults for this kind of tag.
     *
     * `data-navigate-track` is a default rather than something the caller adds, because
     * it is what makes the cache-busting query string mean anything: Livewire
     * full-page-reloads a `wire:navigate` visit when a tracked asset's URL changed, so an
     * upgraded package is picked up instead of new markup running against the file the
     * browser already cached. It is an unknown attribute everywhere else, and
     * `['data-navigate-track' => null]` removes it.
     *
     * @return array<string, string|bool|null>
     */
    public function tagAttributes(): array
    {
        $defaults = ['data-navigate-track' => 'reload'];

        if (! $this->isStylesheet() && ! $this->module) {
            $defaults['defer'] = true;
        }

        return array_merge($defaults, $this->attributes);
    }

    /**
     * Normalize a declared path and reject what cannot be one.
     *
     * Both halves are always package-relative: the shipped file is resolved inside the
     * asset directory and the source inside the package root, and an absolute path or a
     * `..` segment would escape both — silently, into a mirror that copies verbatim.
     */
    private static function clean(string $path, string $kind): string
    {
        $path = trim(str_replace('\\', '/', $path));
        $path = ltrim($path, '/');

        if ($path === '') {
            throw new InvalidArgumentException("Asset $kind cannot be empty");
        }

        if (in_array('..', explode('/', $path), true)) {
            throw new InvalidArgumentException(
                "Asset $kind [$path] must stay inside the package"
            );
        }

        return $path;
    }
}
