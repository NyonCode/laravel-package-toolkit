ex# The Platform — end-to-end walkthrough

How the manifest-based platform works in practice, from three perspectives: package
author, consuming application, and production. Each element is annotated with the
track/release that delivers it.

---

## 1. Package author (Alice builds `alice/blog-engine`)

Scaffold *(Track C — Studio CLI)*:

```bash
nyon new alice/blog-engine
# ? Which resources will the package ship? › config, routes, views, migrations
# ? CI matrix? › PHP 8.3–8.5 × Laravel 12–13
# ✓ Skeleton with Testbench, Pest, Pint, PHPStan, GitHub Actions ready
```

The provider needs only the minimum — or the classic explicit `hasX()` chain; both are
first-class and both produce the same manifest *(Track B — 3.0)*:

```php
class BlogEngineServiceProvider extends PackageServiceProvider
{
    public function configure(Packager $packager): void
    {
        $packager->name('Blog Engine')
            ->discover()                        // conventional layout wires itself
            ->hasConfig(BlogConfig::class);     // typed config on top
    }
}
```

Development loop *(Track C — Studio CLI)*:

```bash
nyon dev    # playground app (testbench serve), file watching — save a view, see it
```

Tests with the toolkit testing kit *(Track B — 3.0)*:

```php
it('publishes what it promises', function () {
    $this->assertPackagePublishes('blog-engine::config', config_path('blog-engine.php'));
    $this->assertPackageRegistersCommand('blog:prune');
    $this->assertPackageManifestCacheable();   // catches closures that would break caching
});
```

Release — this is where the platform's key artifact is produced
*(manifest build: Track B; `nyon release` + protocol: Track C)*:

```bash
nyon release
# ✓ CHANGELOG generated
# ✓ .nyon/manifest.json built
# ✓ Manifest diff against v1.2.0 included in release notes
```

```json
{
  "$schema": "https://nyoncode.dev/schemas/package-manifest/v1.json",
  "name": "blog-engine",
  "provider": "Alice\\BlogEngine\\BlogEngineServiceProvider",
  "resources": {
    "config":     [{ "file": "config/blog-engine.php", "key": "blog-engine",
                     "typed": "Alice\\BlogEngine\\BlogConfig" }],
    "routes":     [{ "file": "routes/web.php" }],
    "views":      { "namespace": "blog-engine", "path": "resources/views" },
    "commands":   [{ "signature": "blog:prune" }],
    "middleware": { "aliases": { "blog.auth": "…" }, "groups": {}, "global": [] }
  },
  "capabilities": ["routes", "commands", "middleware.alias"],
  "publishes":    ["config", "views", "migrations"]
}
```

---

## 2. Consuming application (Bob installs the package)

```bash
composer require alice/blog-engine
php artisan package:audit
```

```
┌──────────────┬────────┬────────────────┬──────────┬───────────────────┐
│ Package      │ Routes │ Middleware     │ Commands │ Flags             │
├──────────────┼────────┼────────────────┼──────────┼───────────────────┤
│ blog-engine  │ 1      │ 1 alias        │ 1        │ –                 │
│ acme-metrics │ 0      │ ⚠ 1 GLOBAL     │ 2        │ ⚠ auto-install    │
└──────────────┴────────┴────────────────┴──────────┴───────────────────┘
```

Bob approves the current state as a lockfile: `php artisan package:audit --lock`
*(Track C — 3.1)*. In application code he uses typed config *(Track B — 3.0)*:

```php
public function index(BlogConfig $config)
{
    return view('blog-engine::index', ['perPage' => $config->perPage]);
    //                                              ^ IDE-completed, boot-validated
}
```

**Six months later**, `composer update` arrives and CI stops it *(Track C — 3.1)*:

```bash
php artisan package:audit --diff --lock
# acme-metrics 2.3.0 → 2.4.0
#   + global middleware Acme\Telemetry\Capture     ← NOT in approved capabilities
# ✗ Audit failed — review before deploying (exit 1)
```

This is the supply-chain moment: a silent behavioral change in a minor release
**cannot pass CI without human approval**.

---

## 3. Production and deployment

Deploy script:

```bash
php artisan package:cache      # all package manifests → one file in bootstrap/cache  (Track B, 3.0)
php artisan package:compile    # AOT fusion into a single generated provider          (Track C, 3.3)
```

```bash
php artisan package:profile    # (Track B, 3.0)
# ┌──────────────┬───────────────┬──────────────┐
# │ Package      │ before        │ after cache  │
# ├──────────────┼───────────────┼──────────────┤
# │ blog-engine  │ 14 fs scans   │ 0 scans      │
# │ acme-metrics │ 6 fs scans    │ 0 scans      │
# │ boot total   │ 8.2 ms        │ 0.4 ms       │
# └──────────────┴───────────────┴──────────────┘
```

---

## 4. Documentation and AI — free by-products of the manifest *(Track C — 3.2)*

```bash
php artisan package:docs blog-engine
# ✓ docs/packages/blog-engine.md   (routes, commands, config keys, publish tags — always current)
# ✓ .phpstorm.meta.php             (autocomplete for config('blog-engine.*') and view('blog-engine::*'))
# ✓ .nyon/llms.txt                 (Copilot/Claude in Bob's IDE knows what the package provides)
```

---

## How it holds together

```
configure()  ──▶  Discovery  ──▶  ResourceManifest  ──▶  Registrar (runtime wiring)
(explicit or                      (serializable
 discover())                       data)  │
                                          ├──▶ package:cache / compile     (performance)
                                          ├──▶ package:audit --diff/--lock (security)
                                          └──▶ package:docs / IDE / AI     (documentation)
```

One investment — turning the package definition into data — and all branches only read
from it. That is why it works identically for explicit and discovered packages, and why
it is hard to copy: a competing toolkit would have to rebuild its model first.

## Walkthrough → track mapping

| Walkthrough element | Track | Release |
|---|---|---|
| `discover()`, `hasConfig(BlogConfig::class)` | B | 3.0 |
| `package:cache`, `package:profile` | B | 3.0 |
| Testing kit (`assertPackagePublishes`, `assertPackageManifestCacheable`) | B | 3.0 |
| `.nyon/manifest.json` as an open JSON standard | C | 3.1 |
| `package:audit`, `--diff`, `--lock` (CI gate) | C | 3.1 |
| `package:docs`, `.phpstorm.meta.php`, llms.txt | C | 3.2 |
| `package:compile` (AOT fusion) | C | 3.3 |
| `nyon new / dev / release` (Studio CLI) | C | separate package |

Track B builds the manifest and uses it internally (performance, testing) — still "a
better toolkit". Track C opens the manifest to the world — a public protocol and the
tools built on it. C cannot exist without B; B is valuable even if C never ships.
