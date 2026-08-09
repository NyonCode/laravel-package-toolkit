# AGENTS.md

Instructions for AI coding agents (Claude Code, Cursor, GitHub Copilot, Codex, Windsurf, and any tool that reads `AGENTS.md`) working with this repository. Single source of truth; tool-specific files (e.g. `CLAUDE.md`) just point here.

## What this is

`nyoncode/laravel-package-toolkit` is a library (not an app) that Laravel package authors extend to wire up their own package's resources — config, routes, migrations, translations, views, view components, middleware, commands, assets — through a fluent API instead of boilerplate. Consumers subclass `PackageServiceProvider` and describe their package inside `configure(Packager $packager)`.

Requires PHP ^8.2 and Laravel 12.x (>= 12.61.1) or 13.x (>= 13.12.0). Laravel 10/11 support was dropped in v2.1 (see README for the CVE rationale).

## Which doc do you need?

- **Using the toolkit** to build a package in another project → [ai/AGENTS.md](./ai/AGENTS.md) — the complete public fluent API, extracted from source, with examples and gotchas. This one **ships with the package**, so it is also what an agent in a consuming project reads out of `vendor/`; keep it accurate when you change the public API.
- **Working on the toolkit itself** (changing/extending it) → [.ai/architecture.md](./.ai/architecture.md) — internal architecture, the trait split, how to add a resource type, test patterns.

## Agent support the package ships

`ai/` is a published part of the package, not scratch notes — [ai/README.md](./ai/README.md) is the
map, including what maintaining it involves. Three pieces, installed into a consuming project by
`vendor/bin/package-toolkit-ai install` (see [bin/package-toolkit-ai](./bin/package-toolkit-ai)):

- `ai/AGENTS.md` — the consumer API reference above.
- `ai/skills/laravel-package-toolkit/SKILL.md` — a Claude Code skill.
- `ai/mcp/server.mjs` — a zero-dependency MCP server that answers from the *installed* source.
  `node ai/mcp/server.mjs --self-test` parses everything and prints a summary; run it after any
  change to the layout of `src/` or `docs/`.

The documentation site generates the machine-readable half from the same Markdown
([site/llms.mjs](./site/llms.mjs)): `/llms.txt`, `/llms-full.txt`, and a `.md` twin of every page.

## Commands

Composer scripts wrap the underlying tools — prefer them:

- `composer test` — run the full Pest suite (`vendor/bin/pest`)
- `composer lint` — run PHPStan (level 5, `src` only)
- `composer pint` — apply Laravel Pint formatting

Running a subset of tests (Pest filters):

- `vendor/bin/pest tests/PackageProviderTests/PackageConfigTest.php` — a single file
- `vendor/bin/pest --filter="registers config"` — by description substring

The suite runs against Orchestra Testbench (a bootstrapped Laravel app), so no separate DB/app setup is needed. `composer build` / `composer serve` build and serve the Testbench workbench app.

## Orientation (the one thing to know)

The library has two collaborators, and traits are split by which one they belong to:

- `src/Concerns/` → mixed into **`Packager`** — *declare* what the package has (`hasConfig()`, `hasRoutes()`, …).
- `src/Support/Concerns/` → mixed into **`PackageServiceProvider`** — *act on* that declaration at register/boot time (`bootRoutes()`, `publishConfig()`, …).

So each feature spans both sides. Full detail — including how to add a new resource type and the test-state reset pattern — is in [.ai/architecture.md](./.ai/architecture.md).

## Conventions

- Every `hasX()` builder returns `static` for chaining and validates eagerly (e.g. `name()` rejects empty, `hasShortName()` enforces kebab-case).
- PHPStan runs at level 5 over `src` only — keep new code passing; tests are not analyzed.
- Formatting is enforced by Pint (`pint.json`); run `composer pint` before finishing.
- Do not attribute commits to any AI tool/vendor in commit messages.
