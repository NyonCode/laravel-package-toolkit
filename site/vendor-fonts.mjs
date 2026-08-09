#!/usr/bin/env node
/**
 * Vendors the site's webfonts into `assets/fonts/` and writes `assets/fonts.css`.
 *
 *   node vendor-fonts.mjs
 *
 * The site used to pull these from `fonts.googleapis.com`, which meant every
 * page load made two third-party requests, the design silently degraded to the
 * system stack whenever that failed, and a reader's IP reached Google on the way
 * to reading documentation. Every family in SOURCE is SIL OFL 1.1, so hosting
 * them ourselves is both permitted and strictly better — check the licence
 * before adding one that is not.
 *
 * Only the `latin` and `latin-ext` subsets are kept — this is an English
 * documentation site, and `latin-ext` is the cheap insurance that covers the
 * author's own name. Google serves one file per subset and, for a variable
 * font, the same file for several weight declarations; identical files are
 * deduplicated and their weight ranges merged.
 *
 * Re-run only to change families or weights. The output is committed.
 */

import fs from 'node:fs'
import path from 'node:path'
import crypto from 'node:crypto'
import { fileURLToPath } from 'node:url'

const here = path.dirname(fileURLToPath(import.meta.url))
const fontsDir = path.join(here, 'assets', 'fonts')

/** Exactly the families and weights `assets/docs.css` asks for. */
const SOURCE =
  'https://fonts.googleapis.com/css2' +
  '?family=Inter+Tight:wght@600..800' +
  '&family=Inter:wght@400..650' +
  '&family=JetBrains+Mono:wght@400;500' +
  '&display=swap'

// Google serves woff2 only to browsers it recognises; an unknown agent gets
// a ttf stylesheet three times the size.
const USER_AGENT =
  'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36'

const KEEP_SUBSETS = ['latin', 'latin-ext']

const slug = (value) => value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '')

async function main() {
  const css = await fetch(SOURCE, { headers: { 'User-Agent': USER_AGENT } }).then((r) => {
    if (!r.ok) throw new Error(`Google Fonts responded ${r.status}`)
    return r.text()
  })

  const blocks = [...css.matchAll(/\/\*\s*([a-z0-9-]+)\s*\*\/\s*@font-face\s*\{([^}]+)\}/g)]
  const faces = []

  for (const [, subset, body] of blocks) {
    if (!KEEP_SUBSETS.includes(subset)) continue

    const field = (name) => (body.match(new RegExp(`${name}:\\s*([^;]+);`)) ?? [])[1]?.trim()
    const url = (body.match(/url\((https:[^)]+\.woff2)\)/) ?? [])[1]

    if (!url) continue

    const bytes = Buffer.from(await fetch(url).then((r) => r.arrayBuffer()))

    faces.push({
      family: field('font-family').replace(/^'|'$/g, ''),
      style: field('font-style') ?? 'normal',
      weight: field('font-weight') ?? '400',
      stretch: field('font-stretch'),
      range: field('unicode-range'),
      subset,
      bytes,
      hash: crypto.createHash('sha1').update(bytes).digest('hex'),
    })
  }

  // A variable font is served once but declared per weight. Collapse those into
  // a single file and a single merged weight range.
  const merged = new Map()

  for (const face of faces) {
    const key = `${face.family}|${face.style}|${face.hash}`
    const existing = merged.get(key)

    if (!existing) {
      merged.set(key, { ...face, weights: [face.weight] })
      continue
    }

    existing.weights.push(face.weight)
  }

  // Clear out the previous vendoring, but only the font files. The OFL requires
  // its text to travel with the binaries it covers, so `LICENSE-*.txt` sits in
  // this directory on purpose — wiping the directory wholesale would drop them
  // and leave the site redistributing fonts with no licence beside them.
  fs.mkdirSync(fontsDir, { recursive: true })
  for (const entry of fs.readdirSync(fontsDir)) {
    if (entry.endsWith('.woff2')) fs.rmSync(path.join(fontsDir, entry))
  }

  const rules = []

  for (const face of merged.values()) {
    const numbers = face.weights.flatMap((w) => w.split(/\s+/).map(Number))
    const weight =
      numbers.length > 1
        ? `${Math.min(...numbers)} ${Math.max(...numbers)}`
        : String(numbers[0])

    const file = `${slug(face.family)}-${slug(weight)}-${face.subset}.woff2`
    fs.writeFileSync(path.join(fontsDir, file), face.bytes)

    rules.push(
      [
        '@font-face {',
        `  font-family: '${face.family}';`,
        `  font-style: ${face.style};`,
        `  font-weight: ${weight};`,
        face.stretch ? `  font-stretch: ${face.stretch};` : null,
        '  font-display: swap;',
        `  src: url('./fonts/${file}') format('woff2');`,
        `  unicode-range: ${face.range};`,
        '}',
      ]
        .filter(Boolean)
        .join('\n'),
    )
  }

  // Named from what was actually fetched, not from a hardcoded list — the
  // families in SOURCE change, and a generated file that misnames its own
  // contents is worse than one with no comment at all.
  const families = [...new Set([...merged.values()].map((face) => face.family))]

  const header = [
    `/* Self-hosted webfonts — ${families.join(', ')}.`,
    ' *',
    ' * All three are SIL OFL 1.1. Vendored rather than requested from Google, so',
    ' * the site makes no third-party request, renders identically offline, and',
    ' * never leaks a reader\'s IP to a third party on the way to the docs.',
    ' *',
    ' * Generated by site/vendor-fonts.mjs — do not edit by hand.',
    ' */',
    '',
  ].join('\n')

  fs.writeFileSync(path.join(here, 'assets', 'fonts.css'), `${header}\n${rules.join('\n\n')}\n`)

  const total = [...merged.values()].reduce((sum, face) => sum + face.bytes.length, 0)

  console.log(
    `  ✓ ${merged.size} font files → site/assets/fonts (${Math.round(total / 1024)}KB)\n` +
      `    stylesheet: site/assets/fonts.css`,
  )
}

main().catch((error) => {
  console.error(`  ✗ ${error.message}`)
  process.exit(1)
})
