# Roadmap

Two tracks, one rule: **nothing gets taken away.**

```
Track A — 2.x evolution      steady, non-breaking improvements; ships continuously
Track B — 3.0 foundation     the Manifest: package definition becomes data
Track C — 3.x platform       what only the Manifest can unlock
```

Track A keeps current users happy and de-risks the project if the platform bet takes
longer than hoped. Tracks B and C are the upside. They share code, not fate.

An end-to-end walkthrough of the platform experience (author → consumer → production)
lives in [PLATFORM.md](PLATFORM.md).

---

## Guiding principle: both configuration approaches are first-class, forever

The explicit fluent API and the new discovery mode are **not** competing options where
one deprecates the other:

```php
// Approach 1 — explicit (stays first-class, never deprecated)
$packager->name('Blog')
    ->hasConfig()
    ->hasRoutes(['api.php'])
    ->hasViews();

// Approach 2 — convention-based (opt-in sugar)
$packager->name('Blog')->discover();

// Mixed — discovery with explicit overrides (explicit always wins)
$packager->name('Blog')
    ->discover(except: ['routes'])
    ->hasRoutes(['api.php']);
```

The design that makes this cheap to promise: **both approaches resolve into the same
`ResourceManifest`.** Discovery is sugar; the manifest is the substance. Every platform
feature — cache, audit, docs, compile — therefore works identically for explicitly
configured packages and for discovered ones. Nobody is forced to migrate to conventions
to benefit from the platform.

---

## Track A — 2.x evolution (parallel, non-breaking)

### Quick wins (2.1.x)

- PHPStan job in CI (tests and Pint already run; `composer lint` now works).
- Guard against duplicate timeless-migration publishing (check for an existing file with
  the same suffix before generating a new timestamp).
- `registerConfig()`: load each config file once instead of twice.
- `escapeshellarg()` for the GitHub-star URL passed to `exec()`.
- Install command: report "nothing to publish" instead of a false "✅ Published" when a
  tag has no registered paths (check `ServiceProvider::pathsToPublish()`).
- README: document routes publish destination, `packageCommands()`, `HasAbout`
  deprecation, fixed `hasViews()` absolute-path behavior.

### Robustness (2.1.x–2.2)

- `HasNamespaceResolver`: replace runtime `require vendor/autoload.php` with
  `ClassLoader::getRegisteredLoaders()` (Composer 2 is already required).
- PHPStan level 6+ (add iterable value types/generics), then PHPStan 2.x.
- Additive exception hierarchy under `PackageConfigurationException` (old types stay as
  parents/aliases until 3.0).

### Parity features (ship in 2.x, carry into 3.0)

- ✓ `hasEvents()` / `hasEvent()` / `hasSubscribers()` / `hasSubscriber()` — **shipped**
- ✓ `hasOptimizeCommands()` (`optimize` / `optimize:clear`) — **shipped**
- `hasBindings()` / `hasSingletons()`
- `hasBladeDirectives()`
- `hasGates()` / `hasPolicies()`
- `hasSchedule()`

### 2.2 — deprecation bridge

`@deprecated` warnings for everything 3.0 removes (`HasAbout`, `bootVewComposers()`,
redundant `InstallCommand` publish aliases, hardcoded `../` conventions), so users
migrate on their own schedule.

---

## Track B — 3.0 foundation: the Manifest

### Auto-discovery (opt-in)

`discover()` walks the package root once and wires the conventional layout: `config/`,
`routes/`, `lang/`, `resources/views/`, `resources/dist/`, `database/migrations/`,
`src/Commands/`, `src/View/Components/`. Explicit `hasX()` overrides; `except:` opts out.

### Resource manifest cache

Today every request re-scans the filesystem during `register()`. Configuration resolves
into a serializable **`ResourceManifest`**, cached like Laravel's own config:

```bash
php artisan package:cache      # zero directory scans afterwards
php artisan package:profile    # per-package boot cost — the measurable, marketable win
```

### Typed config

```php
$packager->hasConfig(BlogConfig::class);   // classes accepted alongside file paths
app(BlogConfig::class)->prefix;            // typed, IDE-completed, validated on boot
```

### Enabling refactor

Split the 18-trait `Packager` god object:
**Packager** (fluent declaration) → **Discovery** (filesystem resolve) →
**ResourceManifest** (serializable data) → **Registrar** (container/blade/router wiring).
Fluent API stays ~95 % source-compatible; closures (hooks, conditionals) remain the
documented non-cacheable escape hatch.

### Breaking changes (only what 2.2 already deprecated)

Remove `HasAbout` + `bootVewComposers()`; unify `directory:` path convention; final
exception hierarchy; canonical `InstallCommand` publish API; installer on laravel/prompts;
baseline PHP 8.3+, PHPStan 2.x level 8 in CI.

---

## Track C — 3.x platform: what the Manifest unlocks

The manifest becomes an open, versioned JSON schema — a **Package Manifest Protocol**
("`package.json` for Laravel packages"). Works for explicit and discovered packages alike.

### `package:audit` — supply-chain security (flagship)

```bash
php artisan package:audit
# blog-engine   routes: 2   middleware: 1 alias   commands: 3   publishes: 4 tags
# acme-metrics  ⚠ registers GLOBAL middleware     ⚠ auto-install on boot

php artisan package:audit --diff
# acme-metrics 2.3.0 → 2.4.0
# + global middleware Acme\Telemetry\Capture   ← review before deploying
```

Manifest diffs across versions catch the classic supply-chain pattern (a minor update
quietly adds middleware/commands) at CI time. Optional lockfile mode
(`package:audit --lock`): approved capabilities, CI fails when exceeded — package
permissions, like browser extensions. Category-creating; a security story travels
further than a DX story.

### `package:docs` — self-documenting packages

Generate from the manifest: README sections/docs pages that cannot drift from the code,
`.phpstorm.meta.php` + IDE completion for config keys and view namespaces, and
machine-readable exports for AI assistants (`llms.txt`-style), so the consumer's
Copilot/Claude knows exactly what an installed package provides.

### `package:compile` — AOT fusion

One step beyond per-package caching: compile all toolkit-based packages of an app into a
single generated provider (bindings, blade components, about data inlined). Target:
package boot cost indistinguishable from hand-written app code, proven by
`package:profile`.

### Studio — `nyon` CLI (separate package)

| Command | Does |
|---------|------|
| `nyon new`     | interactive scaffold: skeleton, testbench, Pest, Pint, PHPStan, CI matrix |
| `nyon dev`     | playground app (testbench serve) with file watching + live re-publish |
| `nyon test`    | toolkit testing kit (`assertPackagePublishes`, `assertPackageManifestCacheable`, …) |
| `nyon docs`    | docs generator with local preview |
| `nyon release` | changelog, version bump, tag, manifest diff in the release notes |

### Ecosystem (moonshot)

Public index of manifest-publishing packages: auto-generated docs pages, capability
badges ("no global middleware, no auto-install"), boot-cost score. Discovery for users,
distribution for authors — the loop that grows protocol adoption.

---

## Platform experience → track mapping

Which part of the [PLATFORM.md](PLATFORM.md) walkthrough each track delivers:

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
Track A is maintenance of current behavior and does not appear in the walkthrough at all.

---

## Combined phasing

| Phase | Track | Deliverable | Release |
|-------|-------|-------------|---------|
| 1 | A | Quick wins + robustness | 2.1.x |
| 2 | A | Parity features (`hasEvents`, `hasBindings`, …) | 2.2 |
| 3 | A | Deprecation bridge | 2.2 |
| 4 | B | Packager split → `ResourceManifest` | 3.0-alpha |
| 5 | B | `discover()` + `package:cache` + `package:profile` | 3.0-beta |
| 6 | B | Typed config, testing kit, prompts installer | 3.0 |
| 7 | C | Manifest Protocol (JSON schema) + `package:audit` (+ `--diff`) | 3.1 |
| 8 | C | `package:docs` + IDE/AI exports | 3.2 |
| 9 | C | `package:compile` (AOT fusion) | 3.3 |
| 10 | C | Studio CLI | separate package |
| 11 | C | Ecosystem index | when 7–10 prove demand |

Strategic rationale: Track A ships value continuously and keeps 2.x users on board;
audit (phase 7) is the marketing spearhead right after 3.0 while attention is high;
docs and AOT deepen the moat; Studio converts authors; the index compounds it.
