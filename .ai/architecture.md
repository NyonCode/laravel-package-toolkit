# Internal architecture (contributor reference for AI agents)

Audience: an AI agent working **on this repository** — changing or extending the toolkit itself. For using the toolkit from another project, see [consuming-the-toolkit.md](./consuming-the-toolkit.md).

## Two collaborators + single-responsibility traits

The library is built from two classes and a large set of traits, split by which class they belong to.

**`PackageServiceProvider` (abstract, `src/PackageServiceProvider.php`)** — the class consumers extend. Drives the Laravel lifecycle:
- `register()`: creates a `Packager` (`bootPackager()`), sets its base path via reflection on the subclass's file location (`getPackageBaseDir()`), calls the consumer's `configure()`, runs conditional callbacks (`executeConditionalCallbacks()`), validates, then `registerConfig()` + `registerInstallCommand()` + `performAutoInstall()`, firing the registering/registered hooks around it.
- `boot()`: `registerPublishing()` → `registerPackageCommands()` → `registerAboutCommand()` → `bootPackageResources()`, wrapped in booting/booted hooks.
- Mixes in the **provider-side** `Support\Concerns\*` traits: `BootsPackageResources` (loads routes/views/migrations/etc. into the running app), `PublishesPackageResources` (registers `publishes()` groups + tags), `HasEnvironmentChecks`, `HasNamespaceResolver`, `HasPublishingTag`.

**`Packager` (`src/Packager.php`)** — the fluent configuration object passed to `configure()`. A bag of state assembled from the **consumer-side** `Concerns\*` traits (`HasConfig`, `HasRoutes`, `HasMigrations`, `HasViews`, `HasCommands`, `HasMiddleware`, `HasEvents`, `HasOptimize`, `HasPublishTagSeparator`, `HasTranslate`, `HasAssets`, `HasProviders`, `HasConditionalLoading`, `HasViewComponents`/`Namespaces`/`Composers`, `HasViewSharedData`, `HasAboutCommand`, `HasInstallation`, plus `Support\Concerns\HasLifecycleHooks`). Each feature trait follows the same shape: a `hasX()` builder that records intent, an `isX()`/`isSetX()` predicate, and a getter. `FilesResolver` underpins them all — cross-platform (Windows-aware) path normalization, discovery, and validation live there.

## The trait split is the key mental model

- `src/Concerns/` → traits mixed into **`Packager`** — *declare* what the package has.
- `src/Support/Concerns/` → traits mixed into **`PackageServiceProvider`** — *act on* that declaration at register/boot time.

A single feature therefore spans both sides. Example — **middleware**:
- `Concerns/HasMiddleware.php` stores aliases/groups/globals and exposes `isSetMiddleware*()` + getters.
- `Support/Concerns/BootsPackageResources::bootMiddleware()` reads them and calls the router/kernel.

## Adding or changing a resource type

Expect to touch several coordinated places:
1. **Declaration** — a `Concerns/HasX.php` trait with `hasX()` builder, an `isX()` predicate, a getter; add the `use` to `Packager`.
2. **Boot** — a `bootX()` method in `Support/Concerns/BootsPackageResources.php`, added to the ordered chain in `bootPackageResources()`.
3. **Publishing** — a `publishX()` method in `Support/Concerns/PublishesPackageResources.php` (tags via `HasPublishingTag::publishTagFormat()`, format `"{shortName}::{group}"`).
4. **Install command** — a matching `publishX()` in `Commands/Concerns/PublishableResources.php` so the install command can select it.

Steps 3–4 apply only to resources that ship files to publish. Register-only resources (e.g. `HasEvents`, `HasOptimize`) need just steps 1–2: a declaration trait plus a `bootX()` in the chain. `bootOptimizes()` forwards to the provider's own `optimizes()` (a `ServiceProvider` method); if a new register-only feature adds static state to `ServiceProvider`, reset it in `PackageServiceProviderTestCase::resetServiceProviderState()` (as done for `optimizeCommands`/`optimizeClearCommands`).

## Install command

`HasInstallation` (Packager side) marks the package installable and builds an `InstallCommand` (`src/Commands/InstallCommand.php`); the provider registers it (`registerInstallCommand()`), or runs it silently when `installOnRun()` is set (`performSilentInstallation()` with `ArrayInput --no-interaction` + `NullOutput`). `Commands/Concerns/PublishableResources.php` holds the publishable-tag selection logic shared with the command. Install-command names are prefixed with the package short name (`{shortName}:install`).

## Lifecycle hooks

`LifecycleHook` enum (`Registering`, `Registered`, `Booting`, `Booted`, `src/Support/Enums/`). Consumers register callbacks on the Packager via `HasLifecycleHooks` (`registeringPackage()` etc.); the provider fires them at the matching lifecycle point through `executeLifecycleHook()`, which no-ops when a hook wasn't defined. Note the name collision: `Packager` methods *set* the callbacks; `PackageServiceProvider` methods of the same name *fire* them.

## Contracts and exceptions

- **Contracts** (`src/Contracts/`) — `Packable`, `ProvidesPackageServices` (extends `Packable`), `HasAbout` define the public surface consumers implement/rely on.
- **Exceptions** — `src/Exceptions/` (`MissingNameException`, `InvalidReturnTypeException`, `InvalidLanguageDirectoryException`) plus top-level `PackageConfigurationException`; thrown from validation paths in `register()`/`registerConfig()`.

## Tests

- `tests/PackageProviderTests/` — integration-style, one file per feature, extending `PackageServiceProviderTestCase`. Each test implements `configure(Packager $packager)`; that closure is injected into the shared `TestServiceProvider` (`tests/TestPackageData/src/`) at runtime via a static `Closure` — this is how one test provider is reconfigured per test.
- `tests/Unit/` — direct unit tests of individual traits/classes.
- `tests/TestPackageData/` — fixture config, routes, migrations, lang, stubs (the "package under test").
- `PackageServiceProviderTestCase::setUp()` resets static provider state (`ServiceProvider::$publishes`/`$publishGroups`/`$publishableMigrationPaths`, the once-only `$isPackageAboutRegistered` flag, `AboutCommand::flushState()`) and cleans published config/migrations between tests — important because provider registration mutates static Laravel state. Follow this pattern when a new feature adds static state.
- Runs against Orchestra Testbench; no separate DB/app setup needed.

## Conventions

- Every `hasX()` builder returns `static` for chaining and validates eagerly (e.g. `name()` rejects empty, `hasShortName()` enforces kebab-case).
- PHPStan runs at **level 5 over `src` only** — keep new code passing; tests are not analyzed.
- Formatting is enforced by Pint (`pint.json`); run `composer pint` before finishing.
- Do not attribute commits to any AI tool/vendor in commit messages.
