#!/usr/bin/env node
/**
 * Builds the documentation site into `site/dist/`.
 *
 *   node build.mjs                 build once
 *   node build.mjs --serve         build, then serve on http://localhost:4321
 *   BASE_URL=/ node build.mjs      build for a domain root instead of a project page
 *
 * Code blocks take one of two paths. With `TORCHLIGHT_TOKEN` in the environment
 * the fenced code is emitted raw and the Torchlight CLI highlights the built
 * HTML in place — real VS Code grammars, real themes, and the `[tl! focus]` /
 * `[tl! ++]` annotations handled by the service that defined them. Without a
 * token the build falls back to `lib/highlight.mjs` and `lib/annotations.mjs`,
 * which produce the same DOM structure so one stylesheet covers both. The build
 * says which path it took.
 */

import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'
import { spawnSync } from 'node:child_process'
import http from 'node:http'

import MarkdownIt from 'markdown-it'
import anchor from 'markdown-it-anchor'
import container from 'markdown-it-container'

import { sections as navSections, pages as navPages } from './nav.mjs'
import { aiEndpoints } from './llms.mjs'
import { icon } from './templates/chrome.mjs'
import { home } from './templates/home.mjs'
import { layout } from './templates/layout.mjs'
import { highlight, escapeHtml } from './lib/highlight.mjs'
import { parseAnnotations, stripAnnotations } from './lib/annotations.mjs'

const here = path.dirname(fileURLToPath(import.meta.url))
const root = path.resolve(here, '..')
const docsDir = path.join(root, 'docs')
const outDir = path.join(here, 'dist')

const BASE = normalizeBase(process.env.BASE_URL ?? '/laravel-package-toolkit/')
const TOKEN = (process.env.TORCHLIGHT_TOKEN ?? '').trim()
const USE_TORCHLIGHT = TOKEN !== ''

const site = {
  title: 'Laravel Package Toolkit',
  description:
    'Build Laravel packages without the boilerplate. Describe config, routes, migrations, views, assets and commands through one fluent API.',
  origin: 'https://nyoncode.github.io',
  repository: 'https://github.com/NyonCode/laravel-package-toolkit',
  editBase: 'https://github.com/NyonCode/laravel-package-toolkit/edit/2.x/docs/',
}

/* ------------------------------------------------------------------ markdown */

/** Filled by the fence renderer and the anchor callback, per page. */
let currentHeadings = []

const md = new MarkdownIt({
  html: true,
  linkify: true,
  breaks: false,
  typographer: false,
})

md.use(anchor, {
  level: [2, 3],
  slugify: slugify,
  permalink: anchor.permalink.linkInsideHeader({
    symbol: '#',
    class: 'heading-anchor',
    placement: 'after',
    ariaHidden: true,
  }),
  callback(token, info) {
    currentHeadings.push({
      level: token.tag === 'h2' ? 2 : 3,
      id: info.slug,
      title: info.title,
    })
  },
})

for (const name of ['note', 'tip', 'warning', 'danger']) {
  md.use(container, name, {
    render(tokens, index) {
      const token = tokens[index]

      if (token.nesting !== 1) {
        return '</div>\n'
      }

      const title = token.info.trim().slice(name.length).trim() || defaultCalloutTitle(name)

      // Rendered as inline markdown so a title can name a method in `code`.
      // The icon repeats what the colour already says, for a reader who cannot
      // rely on the colour.
      return `<div class="callout callout--${name}"><p class="callout__title">${icon[name]}${md.renderInline(title)}</p>\n`
    },
  })
}

function defaultCalloutTitle(name) {
  return { note: 'Note', tip: 'Tip', warning: 'Heads up', danger: 'Careful' }[name]
}

/** Internal links are authored root-relative (`/routes`); rewrite onto the base. */
const defaultLinkOpen =
  md.renderer.rules.link_open ??
  ((tokens, idx, options, env, self) => self.renderToken(tokens, idx, options))

md.renderer.rules.link_open = (tokens, idx, options, env, self) => {
  const token = tokens[idx]
  const href = token.attrGet('href') ?? ''

  if (href.startsWith('/') && !href.startsWith('//')) {
    const [pathname, hash = ''] = href.split('#')
    const trimmed = pathname.replace(/^\/+/, '').replace(/\.md$/, '')
    token.attrSet('href', `${BASE}${trimmed}${trimmed && !trimmed.endsWith('/') ? '/' : ''}${hash ? `#${hash}` : ''}`)
  } else if (/^https?:\/\//.test(href)) {
    token.attrSet('rel', 'noopener noreferrer')
    token.attrSet('target', '_blank')
  }

  return defaultLinkOpen(tokens, idx, options, env, self)
}

/** Tables scroll inside their own box; the page itself never scrolls sideways. */
md.renderer.rules.table_open = () => '<div class="table-scroll"><table>'
md.renderer.rules.table_close = () => '</table></div>'

/**
 * Fenced code.
 *
 * The info string accepts an optional file label:
 *
 *     ```php title="src/BlogServiceProvider.php"
 */
md.renderer.rules.fence = (tokens, idx) => {
  const token = tokens[idx]
  const info = token.info.trim()
  const language = (info.split(/\s+/)[0] || 'text').toLowerCase()
  const titleMatch = info.match(/title="([^"]+)"/)
  const label = titleMatch ? titleMatch[1] : null

  // A block with a filename gets a header naming it; an untitled one gets
  // nothing, so the copy button has the top-right corner to itself.
  const head = label
    ? `<div class="code-block__head"><span class="code-block__file">${escapeHtml(label)}</span><span class="code-block__lang">${escapeHtml(language)}</span></div>`
    : ''

  const body = USE_TORCHLIGHT ? torchlightPlaceholder(token.content, language) : renderLocally(token.content, language)

  return `<div class="code-block" data-language="${escapeHtml(language)}">
  ${head}
  <button class="code-copy" type="button" data-copy aria-label="Copy code to clipboard"><span data-copy-label>Copy</span></button>
  ${body}
</div>
`
}

/**
 * Hand the block to Torchlight untouched. It rewrites the `<pre>` in place,
 * adds the `torchlight` class plus `has-focus-lines` / `has-diff-lines` /
 * `has-summaries`, and leaves the original source behind in a hidden textarea —
 * which is where the copy button reads from.
 */
function torchlightPlaceholder(code, language) {
  return `<pre><code class="language-${escapeHtml(language)}">${escapeHtml(code.replace(/\n$/, ''))}</code></pre>`
}

/**
 * What a folded section shows while it is closed. Kept in step with
 * `options.summaryCollapsedIndicator` in `torchlight.config.cjs`, so a block
 * looks the same whichever path built it.
 */
const COLLAPSED_INDICATOR = '…'

/** The no-token path: annotations and colouring resolved here in the build. */
function renderLocally(code, language) {
  const { lines, flags, sections } = parseAnnotations(code)

  const rendered = lines.map((line) => {
    const html = highlight(line.text, language)
    const classes = ['line', ...line.classes].join(' ')

    return `<div class="${classes}">${html === '' ? '​' : html}</div>`
  })

  const original = escapeHtml(stripAnnotations(code).replace(/\n$/, ''))

  return `<pre><code class="torchlight ${flags.join(' ')}">${fold(rendered, sections)}<textarea data-torchlight-original="true" style="display:none !important;">${original}</textarea></code></pre>`
}

/**
 * Wrap each collapsed range in a `<details>`, leaving every other line where it
 * was. `sections` arrives sorted and non-overlapping, so one pass with a cursor
 * is enough.
 *
 * The `<summary>` carries `line` so it inherits the same padding as the code
 * around it — without it a folded block's caret would sit out of column with
 * the diff gutter.
 */
function fold(rendered, sections) {
  if (sections.length === 0) {
    return rendered.join('')
  }

  const out = []
  let cursor = 0

  for (const section of sections) {
    out.push(...rendered.slice(cursor, section.from))

    const summary = `<summary class="line summary"><span class="summary-caret summary-toggle" aria-hidden="true"></span><span class="summary-indicator summary-hide-when-open">${COLLAPSED_INDICATOR}</span></summary>`

    out.push(
      `<details${section.open ? ' open' : ''}>${summary}${rendered
        .slice(section.from, section.to + 1)
        .join('')}</details>`,
    )

    cursor = section.to + 1
  }

  out.push(...rendered.slice(cursor))

  return out.join('')
}

/**
 * Colour a standalone snippet — the landing page's hero card, which is authored
 * in the template rather than in Markdown. Same two paths as a fenced block, so
 * the hero matches the documentation whichever way the build ran.
 */
function renderCode(code, language) {
  return USE_TORCHLIGHT ? torchlightPlaceholder(code, language) : renderLocally(code, language)
}

/* --------------------------------------------------------------------- pages */

function slugify(value) {
  return value
    .toLowerCase()
    .replace(/<[^>]+>/g, '')
    .replace(/[^\w\- ]+/g, '')
    .trim()
    .replace(/\s+/g, '-')
}

/** Minimal front matter: `key: value` pairs, which is all these pages need. */
function parseFrontMatter(source) {
  const match = source.match(/^---\r?\n([\s\S]*?)\r?\n---\r?\n?/)

  if (!match) {
    return { data: {}, body: source }
  }

  const data = {}

  for (const line of match[1].split(/\r?\n/)) {
    const pair = line.match(/^([A-Za-z_][\w-]*):\s*(.*)$/)

    if (pair) {
      data[pair[1]] = pair[2].replace(/^["']|["']$/g, '').trim()
    }
  }

  return { data, body: source.slice(match[0].length) }
}

function loadPages() {
  return navPages.map((entry) => {
    const file = path.join(docsDir, entry.file)

    if (!fs.existsSync(file)) {
      throw new Error(`Missing documentation page: docs/${entry.file}`)
    }

    const { data, body } = parseFrontMatter(fs.readFileSync(file, 'utf8'))
    const heading = body.match(/^#\s+(.+)$/m)

    return {
      ...entry,
      title: entry.title ?? data.title ?? (heading ? heading[1].trim() : entry.url),
      description: data.description ?? '',
      template: data.layout ?? 'docs',
      body,
    }
  })
}

/** The newest released version, read from the changelog rather than guessed. */
function readVersion() {
  const changelog = path.join(root, 'CHANGELOG.md')

  if (!fs.existsSync(changelog)) {
    return '2.x'
  }

  const match = fs.readFileSync(changelog, 'utf8').match(/^##\s*\[(\d+\.\d+\.\d+)\]/m)

  return match ? match[1] : '2.x'
}

/* --------------------------------------------------------------------- build */

function normalizeBase(value) {
  let base = value.trim()

  if (!base.startsWith('/')) base = `/${base}`
  if (!base.endsWith('/')) base = `${base}/`

  return base
}

function write(file, contents) {
  fs.mkdirSync(path.dirname(file), { recursive: true })
  fs.writeFileSync(file, contents)
}

/**
 * Plain text of a rendered page, for the search index.
 *
 * Chrome around a code block — the filename header, the language label, the
 * copy button — is stripped first. It matches nothing anyone searches for, and
 * left in it turns up as noise in the result snippets.
 */
function textOf(html) {
  return html
    .replace(/<textarea[\s\S]*?<\/textarea>/g, ' ')
    .replace(/<button class="code-copy"[\s\S]*?<\/button>/g, ' ')
    .replace(/<div class="code-block__head[\s\S]*?<\/div>/g, ' ')
    .replace(/<[^>]+>/g, ' ')
    .replace(/&[a-z]+;/g, ' ')
    .replace(/\s+/g, ' ')
    .trim()
}

function buildSearchIndex(rendered) {
  return rendered.map(({ page, html, headings }) => ({
    t: page.title,
    u: `${BASE}${page.url}${page.url ? '/' : ''}`,
    s: page.section,
    d: page.description,
    h: headings.map((heading) => ({ i: heading.id, t: heading.title })),
    b: textOf(html).slice(0, 6000),
  }))
}

function runTorchlight() {
  const binary = path.join(here, 'node_modules', '.bin', 'torchlight')

  if (!fs.existsSync(binary)) {
    console.warn('  ! Torchlight CLI not installed — run `npm install` in site/.')
    return false
  }

  const result = spawnSync(
    binary,
    ['--config', path.join(here, 'torchlight.config.cjs'), '--input', outDir, '--output', outDir],
    { stdio: 'inherit', cwd: here, env: process.env },
  )

  if (result.status !== 0) {
    throw new Error('Torchlight highlighting failed.')
  }

  return true
}

function build() {
  const started = Date.now()
  const version = readVersion()
  const pages = loadPages()

  const sections = navSections.map((section) => ({
    title: section.title,
    pages: section.pages.map((entry) => pages.find((page) => page.file === entry.file)),
  }))

  fs.rmSync(outDir, { recursive: true, force: true })

  const rendered = pages.map((page) => {
    currentHeadings = []

    // The templates render the title from front matter, inside a page header
    // that also carries the section and the description — so the leading `# H1`
    // in the Markdown would be a second copy of it.
    const html = md.render(page.body).replace(/^\s*<h1[^>]*>[\s\S]*?<\/h1>\s*/, '')

    return { page, html, headings: currentHeadings.slice() }
  })

  rendered.forEach(({ page, html, headings }, index) => {
    const editUrl = `${site.editBase}${page.file}`

    const document =
      page.template === 'home'
        ? home({ page, content: html, base: BASE, site, version, renderCode, editUrl })
        : layout({
            page,
            content: html,
            headings,
            sections,
            previous: pages[index - 1] ?? null,
            next: pages[index + 1] ?? null,
            base: BASE,
            site,
            version,
            editUrl,
          })

    write(path.join(outDir, page.url, 'index.html'), document)
  })

  // Assets, then the things GitHub Pages wants.
  fs.cpSync(path.join(here, 'assets'), path.join(outDir, 'assets'), { recursive: true })
  write(path.join(outDir, '.nojekyll'), '')
  write(path.join(outDir, 'assets', 'search-index.json'), JSON.stringify(buildSearchIndex(rendered)))
  write(
    path.join(outDir, 'sitemap.xml'),
    `<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n${pages
      .map((page) => `  <url><loc>${site.origin}${BASE}${page.url}${page.url ? '/' : ''}</loc></url>`)
      .join('\n')}\n</urlset>\n`,
  )
  write(
    path.join(outDir, 'robots.txt'),
    `User-agent: *\nAllow: /\nSitemap: ${site.origin}${BASE}sitemap.xml\n`,
  )

  // The machine-readable half: /llms.txt, /llms-full.txt and a `.md` twin per
  // page. Generated from the same page objects the HTML came from, so the two
  // cannot describe different documentation.
  for (const [file, contents] of aiEndpoints({ pages, sections, site, version, base: BASE })) {
    write(path.join(outDir, file), contents)
  }

  // GitHub Pages serves 404.html for unknown paths.
  const notFound = rendered[0]
  write(
    path.join(outDir, '404.html'),
    layout({
      page: {
        url: '404',
        title: 'Page not found',
        section: 'Prologue',
        description: 'That page has moved, or it never existed.',
      },
      content:
        '<p>Try the navigation, the search (press <kbd>/</kbd>), or start again from the ' +
        '<a href="' + BASE + '">home page</a>.</p>',
      headings: [],
      sections,
      previous: null,
      next: null,
      base: BASE,
      site,
      version,
      editUrl: site.repository,
    }),
  )

  let highlighted = 'built-in fallback highlighter'

  if (USE_TORCHLIGHT) {
    console.log('  → Highlighting with Torchlight…')
    if (runTorchlight()) {
      highlighted = 'Torchlight'
    }
  }

  console.log(
    `\n  ✓ ${pages.length} pages → site/dist  (${Date.now() - started}ms)\n` +
      `    base URL:    ${BASE}\n` +
      `    code blocks: ${highlighted}\n` +
      (USE_TORCHLIGHT
        ? ''
        : '    (set TORCHLIGHT_TOKEN to highlight with Torchlight — https://torchlight.dev)\n'),
  )
}

/* --------------------------------------------------------------------- serve */

const MIME = {
  '.html': 'text/html; charset=utf-8',
  '.css': 'text/css; charset=utf-8',
  '.js': 'text/javascript; charset=utf-8',
  '.json': 'application/json; charset=utf-8',
  '.svg': 'image/svg+xml',
  '.xml': 'application/xml',
  '.txt': 'text/plain; charset=utf-8',
}

function serve(port = 4321) {
  http
    .createServer((request, response) => {
      const url = decodeURIComponent(request.url.split('?')[0])
      const relative = url.startsWith(BASE) ? url.slice(BASE.length) : url.replace(/^\//, '')
      let file = path.join(outDir, relative)

      if (fs.existsSync(file) && fs.statSync(file).isDirectory()) {
        file = path.join(file, 'index.html')
      }

      if (!fs.existsSync(file)) {
        file = path.join(outDir, '404.html')
        response.statusCode = 404
      }

      response.setHeader('Content-Type', MIME[path.extname(file)] ?? 'application/octet-stream')
      response.end(fs.readFileSync(file))
    })
    .listen(port, () => console.log(`  ➜ http://localhost:${port}${BASE}\n`))
}

build()

if (process.argv.includes('--serve')) {
  serve()
}
