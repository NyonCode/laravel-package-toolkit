# Documentation site

The static site behind <https://nyoncode.github.io/laravel-package-toolkit/>.

Content lives in [`../docs`](../docs) as Markdown. This directory holds the generator: a single
Node script, no framework, no runtime dependencies in the output.

```bash
cd site
npm install

npm run build     # → site/dist
npm run dev       # build, then serve on http://localhost:4321
```

## Adding a page

1. Create `docs/your-page.md` with front matter:

   ```markdown
   ---
   title: Your page
   description: One sentence — used for the meta description and the search result.
   ---

   # Your page
   ```

2. Add it to [`nav.mjs`](./nav.mjs), in the section it belongs to. The order there is the sidebar
   order and the previous/next order.

A page that is not in `nav.mjs` is not built. A page in `nav.mjs` with no file is a build error.

## Authoring

**Internal links** are written root-relative and rewritten onto the base URL at build time:

```markdown
See [Migrations](/migrations) and [timeless migrations](/migrations#timeless-migrations).
```

**Code blocks** take an optional filename:

````markdown
```php title="src/BlogServiceProvider.php"
$packager->name('Blog');
```
````

**Callouts** use container syntax. The title is optional and is rendered as inline Markdown, so it
may contain `code`:

```markdown
:::note
Neutral aside.
:::

:::tip Worth knowing
…
:::

:::warning `hasMigrations()` takes an array
…
:::

:::danger
…
:::
```

## The machine-readable build

[`llms.mjs`](./llms.mjs) writes three more things out of the same page objects the HTML comes from,
so the two halves of the site cannot describe different documentation:

| Output | What |
|---|---|
| `/llms.txt` | The [llms.txt](https://llmstxt.org) index — every page as a link with its description |
| `/llms-full.txt` | Every page concatenated, ~220 KB |
| `/<page>.md` | The raw Markdown twin of each page, linked from its HTML `<head>` as `rel="alternate"` |

The one transformation is link rewriting. Pages are authored with root-relative links (`/routes`)
that the HTML build maps onto the base URL; in a text file served without that context they resolve
to nothing, so there they become absolute — and point at the `.md` twin rather than the HTML, so an
agent following one stays in Markdown.

A page not in `nav.mjs` is absent from all of it, same as the HTML. That matters more here: `docs/`
also holds planning documents describing releases that do not exist, and handing those to a model as
documentation is worse than handing it nothing. The MCP server shipped in
[`../ai/mcp`](../ai/mcp/server.mjs) reads `nav.mjs` for exactly that reason.

## Syntax highlighting

Code blocks support [Torchlight](https://torchlight.dev) annotations. Five are wired up here:

| Annotation | Shorthand | What it does | Reach for it when |
|---|---|---|---|
| `[tl! focus]` | `**` | Blurs every other line until the block is hovered or focused | One line in a block you have already shown is the point |
| `[tl! highlight]` | `~~` | Tints the line, leaves the rest readable | Several lines matter, or one matters without the rest being noise |
| `[tl! ++]` | — | Green, with a `+` gutter | A line the reader adds |
| `[tl! --]` | — | Red, with a `−` gutter | A line the reader removes or should not write |
| `[tl! collapse]` | — | Folds the range into a `<details>` | Boilerplate that has to be present to be honest, but not read |
| `[tl! .cls]` | — | Puts the classes on the line, nothing else | A one-off that does not deserve a kind of its own |

````markdown
```php
$packager
    ->name('Blog')
    ->hasConfig();    // [tl! focus]

$packager
    ->hasSeeders()    // [tl! ++]
    ->hasOldThing();  // [tl! --]
```
````

Ranges work as Torchlight documents them: `focus:start` / `focus:end`, `focus:3`, `focus:-2`,
`focus:1,4`, and the same for `add` and `remove`. `**`, `++` and `--` are the shorthands.

The build takes one of two paths, and says which:

| `TORCHLIGHT_TOKEN` | What happens |
|---|---|
| set | Blocks are emitted raw and the [Torchlight CLI](https://torchlight.dev/docs/clients/cli) highlights the built HTML in place — real VS Code grammars, the `material-theme-palenight` theme, annotations handled by the service that defined them. |
| not set | [`lib/highlight.mjs`](./lib/highlight.mjs) and [`lib/annotations.mjs`](./lib/annotations.mjs) resolve the annotations and colour the code locally. |

Both paths emit the same DOM — `.torchlight`, `.line`, `.line-focus`, `.line-add`, `.line-remove`
and the `has-*` flags — so one stylesheet dresses both, and the local fallback is tuned to the same
palette. The fallback is not as good; it exists so a contributor without a token can build and read
the site.

To use Torchlight locally:

```bash
export TORCHLIGHT_TOKEN=...   # free for open source at https://torchlight.dev
npm run build
```

Highlighted blocks are cached in `site/.torchlight-cache` (git-ignored), keyed by content and
options, so an unchanged page costs no API call on the next build.

## Fonts

Inter Tight (display), Inter (body) and JetBrains Mono (code) are **self-hosted** in
`assets/fonts/`, with `assets/fonts.css` generated alongside them:

```bash
node vendor-fonts.mjs
```

They used to be requested from `fonts.googleapis.com`. Vendoring them means the site makes no
third-party request, renders identically offline, and never puts a reader's IP in front of Google on
the way to reading documentation. All three are SIL OFL 1.1, so hosting them is permitted — on the
condition that the licence travels with the files, which is why `assets/fonts/LICENSE-*.txt` sit
beside the `.woff2` and why the vendoring script deletes only `*.woff2` rather than the directory.

Only the `latin` and `latin-ext` subsets are kept — 6 files, about 300KB total, cached after the
first page. Re-run the script only to change families or weights; the output is committed. Its `SOURCE` has to
stay in step with the `--font-*` tokens in `assets/docs.css` — a family the stylesheet asks for and
the script does not fetch fails silently, falling back to the system stack.

## Brand assets

The favicon and touch icons are copied from [`../art`](../art), which is the source of truth for
them:

```bash
cp art/favicon/favicon.svg      site/assets/favicon.svg
cp art/favicon/favicon-180.png  site/assets/favicon-180.png
cp art/favicon/favicon-512.png  site/assets/favicon-512.png
```

`assets/og-image.png` is generated rather than copied: the brand render chain rasterises its own
artboard straight into this directory. The card repeats the landing page's own eyebrow, headline,
accent line and declaration, so the two cannot drift; change the copy there and re-render. It is
referenced by `og:image` and `twitter:image` in `templates/chrome.mjs`.

The masthead and footer mark is the same geometry, inlined in `templates/chrome.mjs` as
`icon.logo(key)`. It takes a key because SVG gradient ids are document-global and the page renders
the mark twice. If the mark ever changes, paste the new paths out of `art/logo/mark.svg`.

That render chain — the Python artboards, the script and the font subsets they read — is
deliberately kept out of the repository. The committed output under `art/logo/` and
`art/favicon/` is all a site build needs.

## Deployment

[`.github/workflows/docs.yml`](../.github/workflows/docs.yml) builds and publishes to GitHub Pages
on a push to `main` or `2.x` that touches `docs/`, `site/` or the changelog, and on manual dispatch.

Two things to set up in the repository once:

- **Settings → Pages → Source: GitHub Actions.**
- **Settings → Secrets → Actions → `TORCHLIGHT_TOKEN`** (optional — the build succeeds without it).

`BASE_URL` is derived from the repository name, so the site works at
`https://<owner>.github.io/<repo>/`. On a custom domain, set `BASE_URL: /` in the workflow and add a
`CNAME` file to `site/assets`.

## How the build works

```text
docs/*.md ─┬─ markdown-it (anchors, containers, custom fence)
           ├─ templates/layout.mjs   → the page shell
           ├─ nav.mjs                → sidebar, pager
           └─ dist/<url>/index.html
                        ├─ assets/            copied verbatim
                        ├─ assets/search-index.json
                        ├─ sitemap.xml, robots.txt, 404.html, .nojekyll
                        └─ (Torchlight pass, if a token is present)
```

| File | Responsibility |
|---|---|
| `build.mjs` | the whole build, plus the dev server |
| `nav.mjs` | table of contents |
| `templates/layout.mjs` | page shell — header, sidebar, prose, on-this-page rail, pager, search dialog |
| `lib/annotations.mjs` | `[tl! …]` parsing for the no-token path |
| `lib/highlight.mjs` | the fallback tokenizer |
| `assets/docs.css` | the entire design — light and dark, mobile first |
| `assets/docs.js` | theme, drawer, scrollspy, copy buttons, search |
| `torchlight.config.cjs` | Torchlight settings (CommonJS — the CLI `require()`s it) |

Search is a JSON index generated at build time and queried client-side, so there is no service to
run and nothing to keep in sync.
