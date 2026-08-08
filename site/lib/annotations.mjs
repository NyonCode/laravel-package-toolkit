/**
 * A local reimplementation of the Torchlight annotations this site uses —
 * `[tl! focus]` and the git-diff pair `[tl! ++]` / `[tl! --]`.
 *
 * It exists for one reason: the real thing is an HTTP API that needs a token,
 * and a contributor without one still has to be able to build and read the
 * site. When `TORCHLIGHT_TOKEN` is set the build ships the raw code blocks and
 * Torchlight does all of this properly (VS Code grammars, real themes); when it
 * is not, this module produces the same *structure* — the same `line-focus`,
 * `line-add`, `line-remove` classes and the same `has-*` flags on the code
 * element — so a single stylesheet dresses both.
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
 * `**` is shorthand for `focus`, `++` for `add`, `--` for `remove`.
 */

const SHORTHAND = { '**': 'focus', '++': 'add', '--': 'remove' }

/** Every annotation directive inside one `[tl! … ]` block. */
const DIRECTIVE = /(\*\*|\+\+|--|[a-zA-Z_]+)(?::(-?\d+)(?:,(\d+))?|:(start|end))?/g

/**
 * The comment wrapper an annotation is allowed to live in. Torchlight lets the
 * annotation ride along in whatever comment the language uses; we strip the
 * wrapper together with the annotation so the published line reads naturally.
 */
const ANNOTATION = /(?:\{\{--|<!--|\/\*|\/\/|#|--|;)?\s*\[tl!([^\]]*)\]\s*(?:--\}\}|-->|\*\/)?/

/**
 * Split annotated source into lines carrying their own flags.
 *
 * @param {string} code
 * @returns {{ lines: {text: string, classes: string[]}[], flags: string[] }}
 */
export function parseAnnotations(code) {
  const raw = code.replace(/\n$/, '').split('\n')
  const lines = raw.map((text) => ({ text, classes: [] }))

  /** Open-ended ranges keyed by kind, holding the index the range started at. */
  const open = new Map()

  raw.forEach((text, index) => {
    const match = text.match(ANNOTATION)

    if (!match) {
      return
    }

    lines[index].text = text.replace(ANNOTATION, '').replace(/\s+$/, '')

    for (const directive of match[1].matchAll(DIRECTIVE)) {
      const kind = SHORTHAND[directive[1]] ?? directive[1]

      if (!['focus', 'add', 'remove'].includes(kind)) {
        continue
      }

      const boundary = directive[4]

      if (boundary === 'start') {
        open.set(kind, index)
        continue
      }

      if (boundary === 'end') {
        mark(lines, kind, open.get(kind) ?? index, index)
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

      mark(lines, kind, from, to)
    }
  })

  // An unterminated `:start` runs to the end of the block, which is what an
  // author who forgot the `:end` almost certainly meant.
  for (const [kind, from] of open) {
    mark(lines, kind, from, lines.length - 1)
  }

  const flags = []
  const has = (cls) => lines.some((line) => line.classes.includes(cls))

  if (has('line-focus')) flags.push('has-focus-lines')
  if (has('line-add')) flags.push('has-add-lines')
  if (has('line-remove')) flags.push('has-remove-lines')
  if (has('line-add') || has('line-remove')) flags.push('has-diff-lines')

  return { lines, flags }
}

function mark(lines, kind, from, to) {
  const cls = `line-${kind}`

  for (let i = Math.max(0, from); i <= Math.min(lines.length - 1, to); i++) {
    if (!lines[i].classes.includes(cls)) {
      lines[i].classes.push(cls)
    }
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
    .map((line) => (ANNOTATION.test(line) ? line.replace(ANNOTATION, '').replace(/\s+$/, '') : line))
    .join('\n')
}
