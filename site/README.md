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

## Syntax highlighting

Code blocks support [Torchlight](https://torchlight.dev) annotations — the two used throughout these
docs are focus lines and git diffs:

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
the way to reading documentation. All three are SIL OFL 1.1, so hosting them is permitted.

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

`assets/og-image.png` is the exception: `art/` was reduced to the logo set and the artboard that
built the Open Graph card went with it. The file still works and is still referenced by `og:image`
in `templates/chrome.mjs`, but it can no longer be regenerated — treat it as a static asset, or drop
the `og:image` / `twitter:image` tags if it ever goes stale.

The masthead and footer mark is the same geometry, inlined in `templates/chrome.mjs` as
`icon.logo(key)`. It takes a key because SVG gradient ids are document-global and the page renders
the mark twice. If the mark changes in `art/artboards/mark.py`, re-run `art/render.sh` and paste the
new paths from `art/logo/mark.svg`.

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
