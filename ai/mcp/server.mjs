#!/usr/bin/env node
/**
 * MCP server for nyoncode/laravel-package-toolkit.
 *
 * Answers questions about the toolkit from the *installed* copy — the docs in
 * `docs/`, the agent guide in `ai/AGENTS.md`, and the signatures and docblocks
 * parsed out of `src/`. That is the whole point: an agent asking `describe_api`
 * gets the method as it exists in the release it is coding against, not as it
 * existed in some training snapshot.
 *
 * Zero dependencies, Node 18+. The MCP stdio transport is newline-delimited
 * JSON-RPC 2.0, which is small enough to implement here rather than pull in an
 * SDK that would have to be `npm install`ed inside `vendor/`.
 *
 *   node ai/mcp/server.mjs            speak MCP on stdin/stdout
 *   node ai/mcp/server.mjs --self-test  parse everything and print a summary
 */

import fs from 'node:fs'
import path from 'node:path'
import readline from 'node:readline'
import { fileURLToPath } from 'node:url'

const here = path.dirname(fileURLToPath(import.meta.url))
const packageRoot = path.resolve(here, '..', '..')
const docsDir = path.join(packageRoot, 'docs')
const srcDir = path.join(packageRoot, 'src')
const guideFile = path.join(packageRoot, 'ai', 'AGENTS.md')

const SERVER = { name: 'laravel-package-toolkit', version: readPackageVersion() }
const FALLBACK_PROTOCOL = '2025-06-18'

/* ----------------------------------------------------------------- reading */

function readPackageVersion() {
  // The installed version is not in composer.json (Composer resolves it from the
  // VCS tag), so the changelog is the only thing on disk that knows.
  try {
    const changelog = fs.readFileSync(path.join(packageRoot, 'CHANGELOG.md'), 'utf8')
    const match = changelog.match(/^##\s*\[(\d+\.\d+\.\d+)\]/m)

    return match ? match[1] : '0.0.0'
  } catch {
    return '0.0.0'
  }
}

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

/**
 * The published pages, in sidebar order.
 *
 * `docs/` also holds pages the site does not build — planning documents that
 * describe releases that do not exist yet. Serving those to an agent is worse
 * than serving nothing, so the site's own table of contents decides what is
 * real. It is read as text rather than imported: one regex has no opinion about
 * whether `site/` survived into the Composer archive, and if it did not, the
 * fallback below is a plain directory listing.
 */
function publishedPages() {
  try {
    const nav = fs.readFileSync(path.join(packageRoot, 'site', 'nav.mjs'), 'utf8')
    const files = [...nav.matchAll(/file:\s*'([^']+\.md)'/g)].map((match) => match[1])

    return files.length > 0 ? files : null
  } catch {
    return null
  }
}

/** Every documentation page plus the agent guide, keyed by slug. */
const documents = (() => {
  const found = new Map()

  const listed = publishedPages()

  const files = listed
    ? listed.filter((name) => fs.existsSync(path.join(docsDir, name)))
    : fs.existsSync(docsDir)
      ? fs
          .readdirSync(docsDir)
          .filter((name) => name.endsWith('.md'))
          .sort()
      : []

  for (const name of files) {
    const slug = name === 'index.md' ? 'overview' : name.replace(/\.md$/, '')
    const source = fs.readFileSync(path.join(docsDir, name), 'utf8')
    const { data, body } = parseFrontMatter(source)
    const heading = body.match(/^#\s+(.+)$/m)

    found.set(slug, {
      slug,
      title: data.title ?? (heading ? heading[1].trim() : slug),
      description: data.description ?? '',
      file: path.relative(packageRoot, path.join(docsDir, name)),
      body: body.trim(),
    })
  }

  if (fs.existsSync(guideFile)) {
    found.set('agent-guide', {
      slug: 'agent-guide',
      title: 'Agent guide — the complete API in one file',
      description: 'Every builder, every gotcha, written for an agent rather than a reader.',
      file: path.relative(packageRoot, guideFile),
      body: fs.readFileSync(guideFile, 'utf8').trim(),
    })
  }

  return found
})()

/* -------------------------------------------------------------- php parsing */

/**
 * Which object a trait's methods end up on.
 *
 * The toolkit splits its traits by collaborator — `src/Concerns` is mixed into
 * `Packager` (declare what the package has), `src/Support/Concerns` into
 * `PackageServiceProvider` (act on that at register/boot). An agent asking for
 * "the methods I can call in configure()" means the first group, so the split
 * has to survive into the answer.
 */
const API_GROUPS = [
  { owner: 'Packager', dir: path.join(srcDir, 'Concerns'), calledFrom: 'configure(Packager $packager)' },
  { owner: 'Packager', file: path.join(srcDir, 'Packager.php'), calledFrom: 'configure(Packager $packager)' },
  {
    owner: 'PackageServiceProvider',
    dir: path.join(srcDir, 'Support', 'Concerns'),
    calledFrom: 'the provider — override to change boot/publish behaviour',
  },
  {
    owner: 'PackageServiceProvider',
    file: path.join(srcDir, 'PackageServiceProvider.php'),
    calledFrom: 'the provider — override to change boot/publish behaviour',
  },
  {
    owner: 'InstallCommand',
    dir: path.join(srcDir, 'Commands', 'Concerns'),
    calledFrom: 'the hasInstallCommand() callback',
  },
  {
    owner: 'InstallCommand',
    file: path.join(srcDir, 'Commands', 'InstallCommand.php'),
    calledFrom: 'the hasInstallCommand() callback',
  },
  { owner: 'Support', dir: path.join(srcDir, 'Support'), calledFrom: 'application code at runtime', shallow: true },
]

/** Collapse a docblock to its prose, dropping the comment furniture and tags. */
function cleanDocblock(block) {
  if (!block) {
    return ''
  }

  const lines = block
    .replace(/^\/\*\*/, '')
    .replace(/\*\/$/, '')
    .split(/\r?\n/)
    .map((line) => line.replace(/^\s*\*\s?/, '').trimEnd())

  const prose = []
  const tags = []

  for (const line of lines) {
    if (/^\s*@/.test(line)) {
      tags.push(line.trim())
    } else if (tags.length === 0) {
      prose.push(line)
    }
  }

  const summary = prose.join('\n').trim().replace(/\n{3,}/g, '\n\n')
  const params = tags.filter((tag) => /^@(param|return|throws|var)\b/.test(tag))

  return [summary, params.join('\n')].filter(Boolean).join('\n\n')
}

/**
 * Public method signatures out of one PHP file.
 *
 * A regex, not a parser: the toolkit's own source is uniformly formatted by
 * Pint, so every declaration is `public function name(...)` with the docblock
 * immediately above it. Anything a regex would get wrong here would also be
 * something Pint would have reformatted.
 */
function parseMethods(file) {
  const source = fs.readFileSync(file, 'utf8')
  const relative = path.relative(packageRoot, file)
  const methods = []

  const pattern = /(\/\*\*[\s\S]*?\*\/)?\s*public\s+(?:static\s+)?function\s+([A-Za-z_]\w*)\s*\(([\s\S]*?)\)\s*(?::\s*([^\n{;]+?))?\s*[{;]/g

  let match

  while ((match = pattern.exec(source)) !== null) {
    const [, doc, name, rawParameters, rawReturn] = match

    if (name === '__construct' || name.startsWith('__')) {
      continue
    }

    const parameters = rawParameters.replace(/\s*\n\s*/g, ' ').replace(/\s{2,}/g, ' ').trim()
    const returns = (rawReturn ?? '').trim()
    const line = source.slice(0, match.index + match[0].indexOf('function')).split('\n').length

    methods.push({
      name,
      signature: `${name}(${parameters})${returns ? `: ${returns}` : ''}`,
      returns,
      doc: cleanDocblock(doc),
      file: relative,
      line,
    })
  }

  return methods
}

function phpFilesIn(dir, shallow) {
  if (!fs.existsSync(dir)) {
    return []
  }

  return fs
    .readdirSync(dir, { withFileTypes: true })
    .flatMap((entry) => {
      const full = path.join(dir, entry.name)

      if (entry.isDirectory()) {
        return shallow ? [] : phpFilesIn(full, shallow)
      }

      return entry.name.endsWith('.php') ? [full] : []
    })
    .sort()
}

/** Every public method the toolkit exposes, grouped by the object it lives on. */
const api = (() => {
  const all = []

  for (const group of API_GROUPS) {
    const files = group.file ? (fs.existsSync(group.file) ? [group.file] : []) : phpFilesIn(group.dir, group.shallow)

    for (const file of files) {
      for (const method of parseMethods(file)) {
        all.push({
          ...method,
          owner: group.owner,
          calledFrom: group.calledFrom,
          declaredIn: path.basename(file, '.php'),
        })
      }
    }
  }

  return all
})()

/* --------------------------------------------------------------- searching */

/** Split a document into `## heading` chunks so a hit can be quoted in context. */
function sectionsOf(document) {
  const lines = document.body.split(/\r?\n/)
  const sections = []
  let current = { heading: document.title, lines: [] }
  let inFence = false

  for (const line of lines) {
    if (/^\s*```/.test(line)) {
      inFence = !inFence
    }

    if (!inFence && /^#{2,3}\s+/.test(line)) {
      sections.push(current)
      current = { heading: line.replace(/^#{2,3}\s+/, '').trim(), lines: [] }
    } else {
      current.lines.push(line)
    }
  }

  sections.push(current)

  return sections.filter((section) => section.lines.join('').trim() !== '')
}

function scoreSection(section, document, terms) {
  const heading = section.heading.toLowerCase()
  const body = section.lines.join('\n').toLowerCase()
  const title = document.title.toLowerCase()

  let score = 0

  for (const term of terms) {
    // A term in the heading is the strongest signal a section is *about* it,
    // rather than merely mentioning it in passing.
    if (heading.includes(term)) score += 10
    if (title.includes(term)) score += 4

    const occurrences = body.split(term).length - 1
    score += Math.min(occurrences, 5)
  }

  // Every term present beats one term present many times.
  const matched = terms.filter((term) => heading.includes(term) || body.includes(term)).length

  return matched === terms.length ? score + 8 : matched === 0 ? 0 : score
}

function searchDocuments(query, limit) {
  const terms = query
    .toLowerCase()
    .split(/[^a-z0-9_:.-]+/)
    .filter((term) => term.length > 1)

  if (terms.length === 0) {
    return []
  }

  const hits = []

  for (const document of documents.values()) {
    for (const section of sectionsOf(document)) {
      const score = scoreSection(section, document, terms)

      if (score > 0) {
        hits.push({ document, section, score })
      }
    }
  }

  return hits.sort((a, b) => b.score - a.score).slice(0, limit)
}

/* ------------------------------------------------------------------- tools */

const tools = [
  {
    name: 'search_docs',
    description:
      'Search the toolkit documentation and agent guide. Returns the matching sections in full, ' +
      'newest-release accurate. Use this first for any "how do I…" question about the toolkit.',
    inputSchema: {
      type: 'object',
      properties: {
        query: { type: 'string', description: 'Words to look for, e.g. "publish tag separator" or "vite assets".' },
        limit: { type: 'integer', description: 'Maximum sections to return (default 5).', minimum: 1, maximum: 20 },
      },
      required: ['query'],
    },
    handler({ query, limit }) {
      const hits = searchDocuments(String(query), Number(limit) || 5)

      if (hits.length === 0) {
        return `No section matches "${query}". Try list_docs to see what pages exist, or list_api for method names.`
      }

      return hits
        .map(
          ({ document, section }) =>
            `## ${section.heading}\n_${document.title} — ${document.file}, read in full with get_doc("${document.slug}")_\n\n${section.lines
              .join('\n')
              .trim()}`,
        )
        .join('\n\n---\n\n')
    },
  },
  {
    name: 'list_docs',
    description: 'List every documentation page available to get_doc, with its one-line description.',
    inputSchema: { type: 'object', properties: {} },
    handler() {
      return [...documents.values()]
        .map((document) => `- \`${document.slug}\` — **${document.title}**${document.description ? `: ${document.description}` : ''}`)
        .join('\n')
    },
  },
  {
    name: 'get_doc',
    description: 'Read one documentation page in full, by the slug from list_docs.',
    inputSchema: {
      type: 'object',
      properties: { slug: { type: 'string', description: 'Page slug, e.g. "assets", "publishing", "agent-guide".' } },
      required: ['slug'],
    },
    handler({ slug }) {
      const document = documents.get(String(slug).replace(/\.md$/, ''))

      if (!document) {
        return `No page "${slug}". Available: ${[...documents.keys()].join(', ')}.`
      }

      return `# ${document.title}\n_${document.file}_\n\n${document.body}`
    },
  },
  {
    name: 'list_api',
    description:
      'List the public methods the toolkit exposes, parsed from the installed source. Grouped by the ' +
      'object they live on: Packager (called in configure()), PackageServiceProvider, InstallCommand, Support.',
    inputSchema: {
      type: 'object',
      properties: {
        owner: {
          type: 'string',
          description: 'Restrict to one object.',
          enum: ['Packager', 'PackageServiceProvider', 'InstallCommand', 'Support'],
        },
        filter: { type: 'string', description: 'Substring the method name must contain, e.g. "asset" or "has".' },
      },
    },
    handler({ owner, filter }) {
      const needle = filter ? String(filter).toLowerCase() : null

      const selected = api.filter(
        (method) =>
          (!owner || method.owner === owner) && (!needle || method.name.toLowerCase().includes(needle)),
      )

      if (selected.length === 0) {
        return 'No method matches. Drop the filter, or try list_api with no arguments.'
      }

      const byOwner = new Map()

      for (const method of selected) {
        if (!byOwner.has(method.owner)) byOwner.set(method.owner, [])
        byOwner.get(method.owner).push(method)
      }

      return [...byOwner]
        .map(([name, methods]) => {
          const calledFrom = methods[0].calledFrom

          return `## ${name}\n_Called from ${calledFrom}._\n\n${methods
            .map((method) => `- \`${method.signature}\`  — ${method.declaredIn}`)
            .join('\n')}`
        })
        .join('\n\n')
    },
  },
  {
    name: 'describe_api',
    description:
      'The exact signature, docblock and source location of one method, parsed from the installed ' +
      'source. Use before calling any builder you are not certain of.',
    inputSchema: {
      type: 'object',
      properties: { method: { type: 'string', description: 'Method name, e.g. "hasViteAssets" or "publishForLocal".' } },
      required: ['method'],
    },
    handler({ method }) {
      const wanted = String(method).replace(/\(.*$/, '').trim().toLowerCase()
      const exact = api.filter((entry) => entry.name.toLowerCase() === wanted)
      const matches = exact.length > 0 ? exact : api.filter((entry) => entry.name.toLowerCase().includes(wanted))

      if (matches.length === 0) {
        return `No method named "${method}". Use list_api with a filter to browse.`
      }

      return matches
        .slice(0, 8)
        .map(
          (entry) =>
            `### \`${entry.owner}::${entry.signature}\`\n\n` +
            `Declared in \`${entry.file}:${entry.line}\` (${entry.declaredIn}).\n` +
            `Called from ${entry.calledFrom}.\n` +
            (entry.doc ? `\n${entry.doc}\n` : '\n_No docblock._\n'),
        )
        .join('\n---\n\n')
    },
  },
]

/* --------------------------------------------------------------- transport */

const toolsByName = new Map(tools.map((tool) => [tool.name, tool]))

function handle(request) {
  const { method, params = {} } = request

  if (method === 'initialize') {
    return {
      protocolVersion: typeof params.protocolVersion === 'string' ? params.protocolVersion : FALLBACK_PROTOCOL,
      capabilities: { tools: { listChanged: false } },
      serverInfo: SERVER,
      instructions:
        'Documentation and API surface for nyoncode/laravel-package-toolkit, read from the copy installed ' +
        'in this project. Prefer these tools over recalling the API: search_docs for a concept, describe_api ' +
        'for an exact signature, get_doc("agent-guide") for the whole thing in one read.',
    }
  }

  if (method === 'ping') {
    return {}
  }

  if (method === 'tools/list') {
    return {
      tools: tools.map(({ name, description, inputSchema }) => ({ name, description, inputSchema })),
    }
  }

  if (method === 'tools/call') {
    const tool = toolsByName.get(params.name)

    if (!tool) {
      throw Object.assign(new Error(`Unknown tool: ${params.name}`), { code: -32602 })
    }

    try {
      return { content: [{ type: 'text', text: tool.handler(params.arguments ?? {}) }] }
    } catch (error) {
      // A tool that blows up reports through the result, not the protocol —
      // that is what lets the model see the message and try something else.
      return { content: [{ type: 'text', text: `Tool failed: ${error.message}` }], isError: true }
    }
  }

  throw Object.assign(new Error(`Unknown method: ${method}`), { code: -32601 })
}

function send(message) {
  process.stdout.write(`${JSON.stringify(message)}\n`)
}

function serve() {
  const input = readline.createInterface({ input: process.stdin })

  input.on('line', (line) => {
    const trimmed = line.trim()

    if (trimmed === '') {
      return
    }

    let request

    try {
      request = JSON.parse(trimmed)
    } catch {
      send({ jsonrpc: '2.0', id: null, error: { code: -32700, message: 'Parse error' } })

      return
    }

    // Notifications carry no id and take no reply.
    if (request.id === undefined || request.id === null) {
      return
    }

    try {
      send({ jsonrpc: '2.0', id: request.id, result: handle(request) })
    } catch (error) {
      send({ jsonrpc: '2.0', id: request.id, error: { code: error.code ?? -32603, message: error.message } })
    }
  })
}

function selfTest() {
  const owners = [...new Set(api.map((method) => method.owner))]

  console.log(`laravel-package-toolkit MCP server ${SERVER.version}`)
  console.log(`  package root : ${packageRoot}`)
  console.log(`  documents    : ${documents.size} (${[...documents.keys()].slice(0, 6).join(', ')}…)`)
  console.log(`  api methods  : ${api.length} across ${owners.join(', ')}`)
  console.log(`  tools        : ${tools.map((tool) => tool.name).join(', ')}`)

  const probe = searchDocuments('publish tag separator', 1)
  console.log(`  search probe : ${probe.length ? `"${probe[0].section.heading}" in ${probe[0].document.slug}` : 'no hit'}`)

  const described = api.find((method) => method.name === 'hasConfig')
  console.log(`  hasConfig    : ${described ? described.signature : 'NOT FOUND'}`)

  if (documents.size === 0 || api.length === 0 || !described) {
    console.error('\n  ✗ Self-test failed — the package layout is not what the server expects.')
    process.exit(1)
  }

  console.log('\n  ✓ Self-test passed.')
}

if (process.argv.includes('--self-test')) {
  selfTest()
} else {
  serve()
}
