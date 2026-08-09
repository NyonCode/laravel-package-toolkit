/**
 * The machine-readable half of the site.
 *
 * Three artefacts, all plain text, all generated from the same Markdown the
 * HTML pages are built from:
 *
 *   /llms.txt        the llms.txt index — title, summary, and every page as a
 *                    link with its one-line description
 *   /llms-full.txt   every page concatenated, for a model that would rather
 *                    read once than fetch thirty times
 *   /<page>.md       the raw Markdown twin of each page, linked from its HTML
 *                    head as `rel="alternate"`
 *
 * The one transformation applied to the Markdown is link rewriting. Pages are
 * authored with root-relative links (`/migrations`) that the HTML build maps
 * onto the base URL; left alone in a `.txt` served from a different host — or
 * pasted into a context window with no host at all — those links resolve to
 * nothing. Here they become absolute, and they point at the `.md` twin rather
 * than the HTML, so an agent following one stays in Markdown.
 */

/** `/migrations#timeless` → `https://host/base/migrations.md#timeless`. */
function absoluteMarkdownLinks(markdown, origin, base) {
  return markdown.replace(/\]\((\/[^)\s]*)\)/g, (whole, href) => {
    if (href.startsWith('//')) {
      return whole
    }

    const [pathname, hash = ''] = href.split('#')
    const slug = pathname.replace(/^\/+/, '').replace(/\.md$/, '')

    return `](${origin}${base}${slug || 'index'}.md${hash ? `#${hash}` : ''})`
  })
}

/** The `.md` twin of a page, at the URL its HTML page advertises. */
export function markdownPath(page) {
  return `${page.url || 'index'}.md`
}

export function markdownUrl(page, origin, base) {
  return `${origin}${base}${markdownPath(page)}`
}

/**
 * The preamble both text files share.
 *
 * It names the other two artefacts and the guide that ships inside the package,
 * because whichever one an agent found first is probably not the one it wants.
 */
function preamble(site, version, origin, base) {
  return `# ${site.title}

> ${site.description}

Version ${version}. Requires PHP ^8.2 and Laravel 12.x (>= 12.61.1) or 13.x (>= 13.12.0).

A package built on the toolkit describes itself once, in \`configure(Packager $packager)\`, and the
base provider does the register, boot and publish work. Every \`hasX()\` on the description has a
matching \`bootX()\` or \`publishX()\` on the provider, and any of them can be overridden.

- Index of every page, with descriptions: ${origin}${base}llms.txt
- Every page in one file: ${origin}${base}llms-full.txt
- Raw Markdown of any page: append \`.md\` to its URL
- Shipped with the package, and therefore always matching the installed release:
  \`vendor/nyoncode/laravel-package-toolkit/ai/AGENTS.md\`, a Claude Code skill, and an MCP server
  that answers from the installed source. Install all three with
  \`vendor/bin/package-toolkit-ai install\`.`
}

function llmsIndex({ sections, site, version, origin, base }) {
  const groups = sections
    .map((section) => {
      const links = section.pages
        .map((page) => {
          const description = page.description ? `: ${page.description}` : ''

          return `- [${page.title}](${markdownUrl(page, origin, base)})${description}`
        })
        .join('\n')

      return `## ${section.title}\n\n${links}`
    })
    .join('\n\n')

  return `${preamble(site, version, origin, base)}

${groups}

## Optional

- [Source repository](${site.repository})
- [Changelog](${site.repository}/blob/main/CHANGELOG.md)
- [Agent guide, rendered on GitHub](${site.repository}/blob/main/ai/AGENTS.md)
`
}

function llmsFull({ pages, site, version, origin, base }) {
  const body = pages
    .map((page) => {
      const heading = `# ${page.title}`
      const meta = `> ${page.section} · ${markdownUrl(page, origin, base)}`
      const markdown = absoluteMarkdownLinks(page.body.trim(), origin, base)

      // The page body opens with its own `# H1`; the heading above replaces it
      // rather than sitting on top of a duplicate.
      const withoutH1 = markdown.replace(/^#\s+.+\r?\n+/, '')

      return `${heading}\n\n${meta}\n\n${withoutH1}`
    })
    .join('\n\n---\n\n')

  return `${preamble(site, version, origin, base)}

---

${body}
`
}

/**
 * Everything the AI side of the build writes, as `[path, contents]` pairs for
 * the caller's `write()` — this module does no I/O of its own, which is what
 * makes it testable from a REPL.
 */
export function aiEndpoints({ pages, sections, site, version, base }) {
  const origin = site.origin
  const files = [
    ['llms.txt', llmsIndex({ sections, site, version, origin, base })],
    ['llms-full.txt', llmsFull({ pages, site, version, origin, base })],
  ]

  for (const page of pages) {
    const front = `# ${page.title}\n\n> ${page.section} · ${site.title} ${version} · ${origin}${base}${page.url}${
      page.url ? '/' : ''
    }\n`
    const markdown = absoluteMarkdownLinks(page.body.trim(), origin, base).replace(/^#\s+.+\r?\n+/, '')

    files.push([markdownPath(page), `${front}\n${markdown}\n`])
  }

  return files
}
