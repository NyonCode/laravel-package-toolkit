/**
 * The offline half of the code pipeline.
 *
 * When `TORCHLIGHT_TOKEN` is set the build hands raw code blocks to Torchlight,
 * which highlights them with real VS Code grammars. This module is what runs
 * when it is not: a small, deliberately conservative tokenizer that colours the
 * handful of languages this documentation actually uses, so a contributor
 * without a token still gets a readable site rather than grey walls of text.
 *
 * It is not trying to be a syntax highlighter for the world. Every rule set
 * below is ordered — comments and strings first, so a `//` inside a string is
 * never mistaken for a comment — and anything unmatched is emitted verbatim.
 */

const escapeHtml = (value) =>
  value
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')

const PHP_KEYWORDS =
  'abstract|and|array|as|break|callable|case|catch|class|clone|const|continue|declare|default|do|echo|else|elseif|empty|enddeclare|endfor|endforeach|endif|endswitch|endwhile|enum|extends|final|finally|fn|for|foreach|function|global|goto|if|implements|include|include_once|instanceof|insteadof|interface|isset|list|match|namespace|new|or|print|private|protected|public|readonly|require|require_once|return|static|switch|throw|trait|try|unset|use|var|while|xor|yield|true|false|null|self|parent|int|string|bool|float|void|iterable|object|mixed|never'

const JS_KEYWORDS =
  'as|async|await|break|case|catch|class|const|continue|default|delete|do|else|export|extends|finally|for|from|function|if|import|in|instanceof|let|new|of|return|static|super|switch|this|throw|try|typeof|var|void|while|yield|true|false|null|undefined'

/**
 * Each language is an ordered list of `[className, RegExp]`. The scanner tries
 * them in order at every position and takes the first that matches.
 */
const GRAMMARS = {
  php: [
    ['com', /\/\*[\s\S]*?\*\//y],
    ['com', /\/\/[^\n]*/y],
    ['com', /#(?!\[)[^\n]*/y],
    ['str', /<<<'?([A-Z_]+)'?\n[\s\S]*?\n\s*\1/y],
    ['str', /'(?:\\.|[^'\\])*'/y],
    ['str', /"(?:\\.|[^"\\])*"/y],
    ['var', /\$[A-Za-z_]\w*/y],
    ['key', new RegExp(`\\b(?:${PHP_KEYWORDS})\\b`, 'y')],
    ['num', /\b\d[\d_]*(?:\.\d+)?\b/y],
    ['fn', /\b[A-Za-z_]\w*(?=\s*\()/y],
    ['cls', /\b[A-Z]\w*\b/y],
    ['op', /(?:=>|->|::|\?\?|\|\||&&|[=+\-*/%.!<>?:|&])/y],
    ['pun', /[{}()[\];,]/y],
  ],
  blade: [
    ['com', /\{\{--[\s\S]*?--\}\}/y],
    ['com', /<!--[\s\S]*?-->/y],
    ['dir', /@[a-zA-Z]+/y],
    ['var', /\{\{[\s\S]*?\}\}/y],
    ['tag', /<\/?[a-zA-Z][\w.:-]*/y],
    ['str', /'(?:\\.|[^'\\])*'/y],
    ['str', /"(?:\\.|[^"\\])*"/y],
    ['attr', /\b[:@]?[a-zA-Z-][\w:-]*(?==)/y],
    ['pun', /\/?>/y],
  ],
  html: [
    ['com', /<!--[\s\S]*?-->/y],
    ['tag', /<\/?[a-zA-Z][\w.:-]*/y],
    ['str', /'(?:\\.|[^'\\])*'/y],
    ['str', /"(?:\\.|[^"\\])*"/y],
    ['attr', /\b[a-zA-Z-][\w:-]*(?==)/y],
    ['pun', /\/?>/y],
  ],
  bash: [
    ['com', /#[^\n]*/y],
    ['str', /'(?:\\.|[^'\\])*'/y],
    ['str', /"(?:\\.|[^"\\])*"/y],
    ['var', /\$\{?[A-Za-z_]\w*\}?/y],
    ['fn', /(?<=^|\n|\||&&|;)\s*(?:npx |npm |composer |php |git |vendor\/bin\/)?[a-z][\w:.-]*/y],
    ['key', /--?[A-Za-z][\w-]*/y],
    ['op', /[|&><]/y],
  ],
  json: [
    ['attr', /"(?:\\.|[^"\\])*"(?=\s*:)/y],
    ['str', /"(?:\\.|[^"\\])*"/y],
    ['key', /\b(?:true|false|null)\b/y],
    ['num', /-?\b\d[\d.eE+-]*\b/y],
    ['pun', /[{}[\],:]/y],
  ],
  yaml: [
    ['com', /#[^\n]*/y],
    ['attr', /(?<=^|\n)\s*-?\s*[\w.$-]+(?=\s*:)/y],
    ['str', /'(?:\\.|[^'\\])*'/y],
    ['str', /"(?:\\.|[^"\\])*"/y],
    ['key', /\b(?:true|false|null|on|off)\b/y],
    ['num', /\b\d[\d.]*\b/y],
    ['pun', /[:[\]{},|>-]/y],
  ],
  ini: [
    ['com', /[#;][^\n]*/y],
    ['attr', /(?<=^|\n)[A-Z_][A-Z0-9_]*(?==)/y],
    ['str', /"(?:\\.|[^"\\])*"/y],
    ['num', /\b\d+\b/y],
    ['op', /=/y],
  ],
  js: [
    ['com', /\/\*[\s\S]*?\*\//y],
    ['com', /\/\/[^\n]*/y],
    ['str', /`(?:\\.|[^`\\])*`/y],
    ['str', /'(?:\\.|[^'\\])*'/y],
    ['str', /"(?:\\.|[^"\\])*"/y],
    ['key', new RegExp(`\\b(?:${JS_KEYWORDS})\\b`, 'y')],
    ['num', /\b\d[\d_.]*\b/y],
    ['fn', /\b[A-Za-z_$][\w$]*(?=\s*\()/y],
    ['cls', /\b[A-Z][\w$]*\b/y],
    ['op', /(?:=>|\?\?|\|\||&&|[=+\-*/%.!<>?:|&])/y],
    ['pun', /[{}()[\];,]/y],
  ],
  css: [
    ['com', /\/\*[\s\S]*?\*\//y],
    ['str', /'(?:\\.|[^'\\])*'/y],
    ['str', /"(?:\\.|[^"\\])*"/y],
    ['var', /--[\w-]+/y],
    ['attr', /[.#]?[\w-]+(?=\s*:)/y],
    ['num', /-?\b\d[\d.]*(?:px|rem|em|%|s|ms|vh|vw)?\b/y],
    ['pun', /[{}();,:]/y],
  ],
}

/** Aliases so a fence can be written the way an author naturally would. */
const ALIASES = {
  shell: 'bash',
  sh: 'bash',
  console: 'bash',
  zsh: 'bash',
  dotenv: 'ini',
  env: 'ini',
  yml: 'yaml',
  javascript: 'js',
  mjs: 'js',
  antlers: 'blade',
  text: null,
  txt: null,
  diff: null,
  '': null,
}

export function resolveGrammar(language) {
  const name = (language ?? '').toLowerCase()
  const resolved = name in ALIASES ? ALIASES[name] : name

  return resolved && GRAMMARS[resolved] ? GRAMMARS[resolved] : null
}

/**
 * Tokenize `code` and return HTML with every token wrapped in a `tok-*` span.
 *
 * Newlines survive tokenization untouched, so the caller can split the result
 * per line afterwards — which is exactly what the line-level focus and diff
 * classes need.
 */
export function highlight(code, language) {
  const grammar = resolveGrammar(language)

  if (!grammar) {
    return escapeHtml(code)
  }

  let out = ''
  let plain = ''
  let index = 0

  while (index < code.length) {
    let matched = null

    for (const [cls, pattern] of grammar) {
      pattern.lastIndex = index
      const result = pattern.exec(code)

      if (result && result.index === index && result[0].length > 0) {
        matched = [cls, result[0]]
        break
      }
    }

    if (matched === null) {
      plain += code[index]
      index += 1
      continue
    }

    if (plain !== '') {
      out += escapeHtml(plain)
      plain = ''
    }

    const [cls, text] = matched
    out += `<span class="tok-${cls}">${escapeHtml(text)}</span>`
    index += text.length
  }

  return out + escapeHtml(plain)
}

export { escapeHtml }
