# `ai/` — agent support that ships with the package

This directory is **published**, not scratch notes. It is installed into every project that requires
the toolkit, and it is what a coding agent working on that project reads.

The problem it solves: an agent asked to add a resource to someone's package reconstructs this
API from whatever release happened to be in its training data, and is confidently wrong about
anything added since. Copying a cheat-sheet into the consuming project only moves the problem — the
copy goes stale at the next release, silently, in the one file nobody re-reads. A reference into
`vendor/` cannot.

| File | What it is | Who reads it |
|---|---|---|
| [`AGENTS.md`](./AGENTS.md) | The complete public API in one file — every builder, load-vs-publish, path resolution, publish tags, the failures that are silent | Any agent, via a reference from the consuming project's own `AGENTS.md` |
| [`skills/laravel-package-toolkit/SKILL.md`](./skills/laravel-package-toolkit/SKILL.md) | A Claude Code skill — the same guidance, loaded when the work calls for it rather than on every turn | Claude Code |
| [`mcp/server.mjs`](./mcp/server.mjs) | An MCP server answering from the *installed* source and docs | Any MCP client |

[`../bin/package-toolkit-ai`](../bin/package-toolkit-ai) wires all three into a consuming project.
Each is independent; none is required.

## Maintaining this — adding or changing a resource type

Adding a resource type to the toolkit touches four places in `src` — a `Concerns/HasX` declaration
trait, a `bootX()` in `Support/Concerns/BootsPackageResources`, a `publishX()` in
`Support/Concerns/PublishesPackageResources`, and the install-command entry — not all four every
time, though: a register-only resource such as events has nothing on disk to copy, so it gets no
`publishX()` and no install-command entry.

**Documenting it is the fifth place, and it is not optional.**

| Where | What |
|---|---|
| [`AGENTS.md`](./AGENTS.md) | A section for the resource, **and** the group added to the list under "Publishing tags" |
| `docs/<resource>.md` | The prose page |
| `site/nav.mjs` | The page's entry — the site does not build a page that is not listed, and neither the MCP server nor `llms.txt` will serve it |

The fifth step is the one with no safety net. Pest does not read these files and PHPStan does not
analyse them, so the failure is not a red build: it is an agent, six months later, in someone else's
repository, telling a package author that the builder you shipped does not exist — and being
believed, because it is quoting the package's own documentation. Nothing about that ever reaches
this repository.

That asymmetry is why the guide travels inside the package instead of being copied into consuming
projects. It only holds while this step is done in the same commit as the code.

The other two artefacts need no editing when the API changes, by construction: the skill
deliberately holds no signatures — it points at `AGENTS.md` — and the MCP server derives everything
it says from `src/` and `docs/`.

## The MCP server

Node 18+, zero dependencies. The MCP stdio transport is newline-delimited JSON-RPC 2.0, which is
less code to implement directly than an SDK would be to `npm install` inside `vendor/`.

```bash
node ai/mcp/server.mjs --self-test    # parse everything, print what was found
node ai/mcp/server.mjs                # speak MCP on stdin/stdout
```

| Tool | Source of the answer |
|---|---|
| `search_docs`, `list_docs`, `get_doc` | `docs/*.md` and `ai/AGENTS.md` |
| `list_api`, `describe_api` | Signatures and docblocks parsed out of `src/` |

Two things it depends on, both of which would degrade it quietly rather than loudly:

- **`src/` stays Pint-formatted.** Methods are matched with a regex, not a parser — `public function
  name(…)` with the docblock immediately above. That holds because Pint enforces it; it is not a
  claim about PHP in general.
- **`site/nav.mjs` decides which pages are real.** `docs/` also holds planning documents describing
  releases that do not exist, and serving those to a model as documentation is worse than serving
  nothing. A page missing from `nav.mjs` is invisible here, exactly as it is on the site. If
  `nav.mjs` cannot be read at all, the server falls back to every `.md` in `docs/` — losing that
  filter, which is why the CI check asserts a page count rather than merely a non-zero one.

`--self-test` covers both: it fails if the document set is empty, if no methods parsed, or if
`hasConfig` — a signature that has been stable for the life of the package — cannot be found. CI
runs it on every change to `src/`, `docs/`, `ai/` or `site/nav.mjs`.

## Reading the docs without installing anything

The documentation site publishes itself in machine-readable form:
[`/llms.txt`](https://nyoncode.github.io/laravel-package-toolkit/llms.txt) (index),
[`/llms-full.txt`](https://nyoncode.github.io/laravel-package-toolkit/llms-full.txt) (everything),
and a `.md` twin of every page. Generated by [`../site/llms.mjs`](../site/llms.mjs) from the same
Markdown as the HTML.
