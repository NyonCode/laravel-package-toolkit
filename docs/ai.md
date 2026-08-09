---
title: AI agents
description: Give the coding agent working on your package an accurate, version-matched description of the toolkit — a guide in vendor, a Claude Code skill, and an MCP server.
---

# AI agents

An agent asked to add a resource to your package will guess at the toolkit's API, and a guess is
drawn from whatever release happened to be in its training data. The toolkit therefore ships its own
documentation for agents, inside the package, so what the agent reads is the version in your
`composer.lock`.

One command wires up all of it:

```bash
vendor/bin/package-toolkit-ai install
```

```
Wiring agent support into /home/you/packages/blog-engine

  ✓ appended a block to AGENTS.md
  ✓ created .claude/skills/laravel-package-toolkit/SKILL.md
  ✓ registered the server in .mcp.json
```

It is idempotent — run it again after upgrading the toolkit and it refreshes what changed and
reports the rest as unchanged. `status` shows what is wired up, `remove` takes it back out, and
`--dry-run` prints the changes without writing them.

:::tip Nothing is required
Each of the three works on its own. Skip any of them with `--no-skill` / `--no-mcp`, or write your
own `AGENTS.md` pointing at the guide by hand — the file is just sitting in `vendor/`.
:::

## What gets installed

### The guide, referenced from `AGENTS.md`

`AGENTS.md` is the convention most coding agents read — Claude Code, Cursor, Codex, Copilot,
Windsurf and Zed among them. The installer adds a delimited block to yours (creating the file if
you have none) that points at the reference shipped in the package:

```markdown
<!-- laravel-package-toolkit:start -->
## Building this package with laravel-package-toolkit
…
[vendor/nyoncode/laravel-package-toolkit/ai/AGENTS.md](vendor/nyoncode/laravel-package-toolkit/ai/AGENTS.md)
<!-- laravel-package-toolkit:end -->
```

The markers are what make the block safe: an upgrade rewrites only what is between them, `remove`
deletes only that, and everything you wrote around it is left alone.

That guide is the complete public API in one file — every builder, which resources load versus
publish versus both, how paths resolve against the provider file, the publish-tag format, and the
failures that are silent rather than loud.

### A Claude Code skill

`.claude/skills/laravel-package-toolkit/SKILL.md`. A skill loads when the work calls for it rather
than on every turn, so the detail is there while an agent is writing a `configure()` body and out of
the way the rest of the time.

### An MCP server

`.mcp.json` gains a `laravel-package-toolkit` entry running `ai/mcp/server.mjs`. It needs Node 18+
and has no dependencies of its own — the MCP stdio protocol is small enough that the server
implements it directly rather than vendoring an SDK inside `vendor/`.

Five tools:

| Tool | Answers |
|---|---|
| `search_docs` | "How do I…" — returns the matching documentation sections in full |
| `list_docs` | Every page available to `get_doc` |
| `get_doc` | One page, whole; `get_doc("agent-guide")` is the complete API in one read |
| `list_api` | Public methods, grouped by `Packager` / `PackageServiceProvider` / `InstallCommand` |
| `describe_api` | One method's exact signature, docblock and `file:line` |

`list_api` and `describe_api` parse the installed `src/`, so they describe the release you have
rather than the release the model remembers. Check it works with:

```bash
node vendor/nyoncode/laravel-package-toolkit/ai/mcp/server.mjs --self-test
```

## Reading the documentation directly

The site publishes itself in machine-readable form as well, for an agent with web access and no
toolkit installed yet:

| URL | What |
|---|---|
| [`/llms.txt`](https://package-toolkit.nyoncode.cz/llms.txt) | Index — every page as a link with its one-line description |
| [`/llms-full.txt`](https://package-toolkit.nyoncode.cz/llms-full.txt) | Every page concatenated, ~220 KB |
| `<page-url>.md` | The raw Markdown of any page — [`/assets.md`](https://package-toolkit.nyoncode.cz/assets.md), [`/publishing.md`](https://package-toolkit.nyoncode.cz/publishing.md) |

Every HTML page links its own Markdown twin from `<head>` as
`<link rel="alternate" type="text/markdown">`, and links inside the Markdown are absolute and point
at other `.md` files, so following one keeps you in Markdown.

## Shipping agent support in your own package

Nothing here is toolkit-specific machinery you can reuse directly, but the shape transfers: put a
single Markdown file describing your package's API somewhere inside the published package, and
reference it from the consuming project's `AGENTS.md` rather than copying it. A copy goes stale at
the next release; a reference into `vendor/` cannot.

If your package publishes stubs or generators an agent would otherwise have to guess at,
[`hasStubs()`](/stubs) puts them somewhere it can read them.
