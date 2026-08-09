/**
 * A local reimplementation of the Torchlight annotations this site uses —
 * `[tl! focus]`, `[tl! highlight]`, `[tl! collapse]`, the git-diff pair
 * `[tl! ++]` / `[tl! --]`, and bare custom classes such as `[tl! .warn]`.
 *
 * It exists for one reason: the real thing is an HTTP API that needs a token,
 * and a contributor without one still has to be able to build and read the
 * site. When `TORCHLIGHT_TOKEN` is set the build ships the raw code blocks and
 * Torchlight does all of this properly (VS Code grammars, real themes); when it
 * is not, this module produces the same *structure* — the same `line-focus`,
 * `line-highlight`, `line-add`, `line-remove` classes, the same `<details>` /
 * `<summary>` pair for a collapsed section and the same `has-*` flags on the
 * code element — so a single stylesheet dresses both.
 *
 * Range syntax follows https://torchlight.dev/docs/annotations:
 *
 *   [tl! focus]        this line only
 *   [tl! focus:start]  … [tl! focus:end]   an inclusive range
 *   [tl! focus:10]     this line plus the 10 that follow
 *   [tl! focus:-10]    this line plus the 10 that precede it
 *   [tl! focus:1,10]   start one line down, 10 lines in total
 *   [tl! focus:-1,10]  start one line up, 10 lines in total
 *
 * `**` is shorthand for `focus`, `~~` for `highlight`, `++` for `add`, `--` for
 * `remove`. Every kind takes the same ranges.
 *
 * Two kinds behave specially:
 *
 *   [tl! collapse:start open] … [tl! collapse:end]
 *     Folds the range into a `<details>` element. The trailing `open` keyword
 *     ships it expanded, so the lines read normally and the reader can *hide*
 *     them rather than reveal them.
 *
 *   [tl! .warn.is-stale]
 *     Puts arbitrary classes on the line and nothing else — the escape hatch
 *     for a one-off that does not deserve a kind of its own.
 *
 * Several directives can share one annotation (`[tl! focus, .warn]`), and a
 * line may carry more than one annotation block.
 */

const SHORTHAND = { '**': 'focus', '~~': 'highlight', '++': 'add', '--': 'remove' }

/** The kinds that resolve to a `line-<kind>` class on every line in range. */
const LINE_KINDS = ['focus', 'highlight', 'add', 'remove']

/** Every annotation directive inside one `[tl! … ]` block. */
const DIRECTIVE = /(\*\*|~~|\+\+|--|\.[\w.-]+|[a-zA-Z_]+)(?::(-?\d+)(?:,(\d+))?|:(start|end))?/g

/**
 * The comment wrapper an annotation is allowed to live in. Torchlight lets the
 * annotation ride along in whatever comment the language uses; we strip the
 * wrapper together with the annotation so the published line reads naturally.
 *
 * Global, because a line is allowed to carry more than one block.
 */
const ANNOTATION = /(?:\{\{--|<!--|\/\*|\/\/|#|--|;)?[ \t]*\[tl!([^\]]*)\][ \t]*(?:--\}\}|-->|\*\/)?/g

/**
 * Split annotated source into lines carrying their own flags.
 *
 * `sections` describes the collapsed ranges, in source order and already
 * de-overlapped — the renderer walks it alongside `lines` to nest the folded
 * runs inside a `<details>`.
 *
 * @param {string} code
 * @returns {{
 *   lines: {text: string, classes: string[]}[],
 *   flags: string[],
 *   sections: {from: number, to: number, open: boolean}[],
 * }}
 */
export function parseAnnotations(code) {
  const raw = code.replace(/\n$/, '').split('\n')
  const lines = raw.map((text) => ({ text, classes: [] }))

  /** Open-ended ranges keyed by kind, holding the index the range started at. */
  const open = new Map()

  /** Collapsed ranges, collected as they close and sorted afterwards. */
  const sections = []

  /** `[tl! collapse:start open]` — remembered until the matching `:end`. */
  const expanded = new Set()

  const collapse = (from, to, isOpen) => {
    sections.push({ from: Math.max(0, from), to: Math.min(lines.length - 1, to), open: isOpen })
  }

  raw.forEach((text, index) => {
    if (!text.includes('[tl!')) {
      return
    }

    lines[index].text = text.replace(ANNOTATION, '').replace(/\s+$/, '')

    for (const [, body] of text.matchAll(ANNOTATION)) {
      // `open` is a modifier on `collapse`, not a directive of its own, so it
      // is read off the whole block rather than from the directive loop.
      const isOpen = /(^|[\s,])open([\s,]|$)/.test(body)

      for (const directive of body.matchAll(DIRECTIVE)) {
        const token = directive[1]

        // `[tl! .foo.bar]` — classes verbatim, no range, no flag.
        if (token.startsWith('.')) {
          for (const cls of token.slice(1).split('.').filter(Boolean)) {
            add(lines[index], cls)
          }

          continue
        }

        const kind = SHORTHAND[token] ?? token

        if (!LINE_KINDS.includes(kind) && kind !== 'collapse') {
          continue
        }

        const boundary = directive[4]

        if (boundary === 'start') {
          open.set(kind, index)

          if (kind === 'collapse' && isOpen) {
            expanded.add(index)
          }

          continue
        }

        if (boundary === 'end') {
          const from = open.get(kind) ?? index

          if (kind === 'collapse') {
            collapse(from, index, expanded.has(from) || isOpen)
            expanded.delete(from)
          } else {
            mark(lines, kind, from, index)
          }

          open.delete(kind)
          continue
        }

        const offset = directive[2] === undefined ? 0 : Number(directive[2])
        const count = directive[3] === undefined ? undefined : Number(directive[3])

        // `focus:10` means this line *plus* ten; `focus:1,10` means ten lines
        // in total, starting one line down.
        const [from, to] =
          count === undefined
            ? offset < 0
              ? [index + offset, index]
              : [index, index + offset]
            : [index + offset, index + offset + count - 1]

        if (kind === 'collapse') {
          collapse(from, to, isOpen)
        } else {
          mark(lines, kind, from, to)
        }
      }
    }
  })

  // An unterminated `:start` runs to the end of the block, which is what an
  // author who forgot the `:end` almost certainly meant.
  for (const [kind, from] of open) {
    if (kind === 'collapse') {
      collapse(from, lines.length - 1, expanded.has(from))
    } else {
      mark(lines, kind, from, lines.length - 1)
    }
  }

  const flags = []
  const has = (cls) => lines.some((line) => line.classes.includes(cls))

  if (has('line-focus')) flags.push('has-focus-lines')
  if (has('line-highlight')) flags.push('has-highlight-lines')
  if (has('line-add')) flags.push('has-add-lines')
  if (has('line-remove')) flags.push('has-remove-lines')
  if (has('line-add') || has('line-remove')) flags.push('has-diff-lines')

  const folded = resolveSections(sections)

  // Torchlight's own name for "this block contains a `<details>`".
  if (folded.length > 0) flags.push('has-summaries')

  return { lines, flags, sections: folded }
}

/**
 * Sort collapsed ranges and drop any that overlaps one already kept.
 *
 * Nesting a `<details>` inside another would need the renderer to keep a stack
 * for a case no page has ever wanted; dropping the overlap keeps the outer
 * fold, which is the one an author writing two of them meant to keep.
 */
function resolveSections(sections) {
  const kept = []

  for (const section of [...sections].sort((a, b) => a.from - b.from || b.to - a.to)) {
    if (section.to < section.from) {
      continue
    }

    if (kept.some((other) => section.from <= other.to && other.from <= section.to)) {
      continue
    }

    kept.push(section)
  }

  return kept
}

function mark(lines, kind, from, to) {
  for (let i = Math.max(0, from); i <= Math.min(lines.length - 1, to); i++) {
    add(lines[i], `line-${kind}`)
  }
}

function add(line, cls) {
  if (!line.classes.includes(cls)) {
    line.classes.push(cls)
  }
}

/**
 * Strip every annotation from a block, leaving the code a reader would copy.
 *
 * Used for the copy-to-clipboard payload and for the search index, where a
 * stray `[tl! focus]` would be noise in both.
 */
export function stripAnnotations(code) {
  return code
    .split('\n')
    .map((line) => (line.includes('[tl!') ? line.replace(ANNOTATION, '').replace(/\s+$/, '') : line))
    .join('\n')
}
