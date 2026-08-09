/**
 * The parts of the page that are the same on every URL.
 *
 * Two templates render pages — `layout.mjs` for documentation, `home.mjs` for the
 * landing page — and both need the same head, masthead, search dialog and script
 * tags. They live here so the two templates cannot drift apart: a change to the
 * theme bootstrap or the search markup is made once.
 *
 * Everything is styled with Tailwind utilities written into the markup. Class
 * names that are *not* utilities — `nav-link`, `code-block`, `search-result` and
 * the `is-*` state classes — are hooks `assets/docs.js` queries by name; they
 * carry no styling of their own, and the states they represent are styled from
 * here with the `[&.is-x]:` variant.
 */

export const escape = (value) =>
  String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')

export const icon = {
  github: `<svg viewBox="0 0 16 16" aria-hidden="true" width="18" height="18" fill="currentColor"><path d="M8 0C3.58 0 0 3.58 0 8a8 8 0 0 0 5.47 7.59c.4.07.55-.17.55-.38l-.01-1.49c-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82a7.4 7.4 0 0 1 2-.27c.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48l-.01 2.2c0 .21.15.46.55.38A8.01 8.01 0 0 0 16 8c0-4.42-3.58-8-8-8Z"/></svg>`,
  sun: `<svg viewBox="0 0 24 24" aria-hidden="true" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>`,
  moon: `<svg viewBox="0 0 24 24" aria-hidden="true" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"/></svg>`,
  menu: `<svg viewBox="0 0 24 24" aria-hidden="true" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg>`,
  close: `<svg viewBox="0 0 24 24" aria-hidden="true" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>`,
  search: `<svg viewBox="0 0 24 24" aria-hidden="true" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>`,
  /* The disclosure arrow on a `<details>`. Drawn rather than typed: at the size
     these summaries need, `▸` renders as a smudge on most platforms. */
  chevron: `<svg viewBox="0 0 24 24" aria-hidden="true" width="11" height="11" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m9 5 7 7-7 7"/></svg>`,
  arrowLeft: `<svg viewBox="0 0 24 24" aria-hidden="true" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M11 18l-6-6 6-6"/></svg>`,
  arrowRight: `<svg viewBox="0 0 24 24" aria-hidden="true" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>`,
  copy: `<svg viewBox="0 0 24 24" aria-hidden="true" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="11" height="11" rx="2.5"/><path d="M15 5.5A2.5 2.5 0 0 0 12.5 3H6.5A2.5 2.5 0 0 0 4 5.5v6A2.5 2.5 0 0 0 6.5 14"/></svg>`,
  check: `<svg viewBox="0 0 24 24" aria-hidden="true" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m4 12.5 5 5L20 6.5"/></svg>`,
  note: `<svg viewBox="0 0 24 24" aria-hidden="true" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 11v5.5M12 7.75v.5"/></svg>`,
  tip: `<svg viewBox="0 0 24 24" aria-hidden="true" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18h6M10 21h4M12 3a6 6 0 0 0-3.5 10.9V16h7v-2.1A6 6 0 0 0 12 3Z"/></svg>`,
  warning: `<svg viewBox="0 0 24 24" aria-hidden="true" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4.5 2.8 20h18.4L12 4.5Z"/><path d="M12 10v4.25M12 17.4v.4"/></svg>`,
  danger: `<svg viewBox="0 0 24 24" aria-hidden="true" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="m8.75 8.75 6.5 6.5M15.25 8.75l-6.5 6.5"/></svg>`,
  /**
   * The brand mark — an isometric carton, the same geometry as
   * `art/logo/mark.svg`, which is where a regenerated mark lands. If it changes
   * there, paste the new paths here.
   *
   * Takes a key because the page renders it more than once and SVG gradient
   * ids are document-global — two copies sharing `lpt-top` is invalid markup
   * and breaks the moment the two instances stop being identical.
   */
  logo: (key = 'a', size = 28) =>
    `<svg viewBox="0 0 64 64" aria-hidden="true" width="${size}" height="${size}" fill="none"><defs><linearGradient id="lpt-${key}-top" x1="4" y1="6" x2="60" y2="58" gradientUnits="userSpaceOnUse"><stop stop-color="#f87171"/><stop offset="1" stop-color="#ef4444"/></linearGradient><linearGradient id="lpt-${key}-right" x1="4" y1="6" x2="60" y2="58" gradientUnits="userSpaceOnUse"><stop stop-color="#dc2626"/><stop offset="1" stop-color="#c11d1d"/></linearGradient><linearGradient id="lpt-${key}-left" x1="4" y1="6" x2="60" y2="58" gradientUnits="userSpaceOnUse"><stop stop-color="#991b1b"/><stop offset="1" stop-color="#7f1616"/></linearGradient></defs><path d="M32.6 4.35L55.15 17.36A1.2 1.2 0 0 1 55.15 19.44L45.95 24.75A1.2 1.2 0 0 1 44.75 24.75L22.2 11.74A1.2 1.2 0 0 1 22.2 9.66L31.4 4.35A1.2 1.2 0 0 1 32.6 4.35Z" fill="url(#lpt-${key}-top)"/><path d="M18.5 12.49L41.05 25.5A1.2 1.2 0 0 1 41.05 27.58L32.6 32.46A1.2 1.2 0 0 1 31.4 32.46L8.85 19.44A1.2 1.2 0 0 1 8.85 17.36L17.3 12.49A1.2 1.2 0 0 1 18.5 12.49Z" fill="url(#lpt-${key}-top)"/><path d="M55.9 20.74L33.35 33.76A1.2 1.2 0 0 0 32.75 34.8L32.75 55.49A1.2 1.2 0 0 0 34.55 56.53L57.1 43.51A1.2 1.2 0 0 0 57.7 42.47L57.7 21.78A1.2 1.2 0 0 0 55.9 20.74Z" fill="url(#lpt-${key}-right)"/><path d="M8.1 20.74L30.65 33.76A1.2 1.2 0 0 1 31.25 34.8L31.25 55.49A1.2 1.2 0 0 1 29.45 56.53L6.9 43.51A1.2 1.2 0 0 1 6.3 42.47L6.3 21.78A1.2 1.2 0 0 1 8.1 20.74Z" fill="url(#lpt-${key}-left)"/></svg>`,
}

/**
 * The frame around a highlighted block.
 *
 * `code-block` and `code-copy` are styled in the component layer of
 * `assets/tailwind.css`, because most blocks come from a Markdown fence and there
 * is nowhere in a fence to put a class. `group` is the exception: it is a
 * Tailwind marker, and the copy button's `group-hover:` needs it here.
 *
 * Both the fence renderer in `build.mjs` and the hand-authored snippets on the
 * landing page go through this, so a block cannot be framed two different ways.
 */
export function codeBlock(inner, language, copyLabel = 'Copy code to clipboard') {
  return `<div class="code-block group" data-language="${escape(language)}">
  <button class="code-copy" type="button" data-copy aria-label="${escape(copyLabel)}"><span data-copy-label>Copy</span></button>
  ${inner}
</div>
`
}

/** The shared shape of every square control in the masthead. */
const ICON_BUTTON =
  'inline-flex size-9 shrink-0 items-center justify-center rounded-md text-muted no-underline ' +
  'transition-colors hover:bg-sunken hover:text-ink pointer-coarse:size-11'

/** The same control, on the landing page's band. */
const ICON_BUTTON_INK =
  'inline-flex size-9 shrink-0 items-center justify-center rounded-md text-band-muted no-underline ' +
  'transition-colors hover:bg-band-soft-strong hover:text-band-ink pointer-coarse:size-11'

/**
 * Everything up to and including `<body>`.
 *
 * The theme is resolved inline, before the body is parsed, so a dark-mode reader
 * never gets a white flash. Fonts are local (`assets/fonts.css`, written by
 * `vendor-fonts.mjs`), so there is nothing to preconnect to and nothing that can
 * drop the page to the system stack when a third party is slow.
 */
export function documentHead({ title, description, canonical, base, site, bodyClass, pageId, markdown }) {
  return `<!doctype html>
<html lang="en" class="no-js">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>${escape(title)}</title>
<meta name="description" content="${escape(description)}">
<link rel="canonical" href="${escape(canonical)}">
${markdown ? `<link rel="alternate" type="text/markdown" href="${escape(markdown)}" title="${escape(title)} as Markdown">\n` : ''}<meta name="color-scheme" content="light dark">
<meta name="theme-color" content="#0b0b0d">
<meta property="og:type" content="website">
<meta property="og:site_name" content="${escape(site.title)}">
<meta property="og:title" content="${escape(title)}">
<meta property="og:description" content="${escape(description)}">
<meta property="og:url" content="${escape(canonical)}">
<meta property="og:image" content="${escape(site.origin)}${base}assets/og-image.png">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="Laravel Package Toolkit — describe your package once, the service provider writes itself.">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:image" content="${escape(site.origin)}${base}assets/og-image.png">
<link rel="icon" href="${base}assets/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="${base}assets/favicon-180.png">
<link rel="stylesheet" href="${base}assets/fonts.css">
<link rel="stylesheet" href="${base}assets/docs.css">
<script>
(function () {
  var root = document.documentElement
  root.classList.remove('no-js')
  try {
    var stored = localStorage.getItem('lpt-theme')
    if (stored === 'light' || stored === 'dark') { root.dataset.theme = stored }
  } catch (e) {}
})()
</script>
</head>
<body data-page="${escape(pageId)}" class="${bodyClass ? `${escape(bodyClass)} ` : ''}min-h-dvh">
<a class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-100 focus:rounded-md focus:border focus:border-line focus:bg-surface focus:px-4 focus:py-2 focus:text-sm focus:font-medium focus:text-ink focus:no-underline focus:shadow-lg" href="#content">Skip to content</a>
`
}

/**
 * The masthead.
 *
 * On a documentation page it has no ground of its own until the reader has
 * moved: it is transparent at the top, and `docs.js` adds `is-scrolled` past the
 * first few pixels, which brings in the blur and the hairline.
 *
 * `onInk: true` is the landing page, which has its own ground one step away from
 * the documentation's. There the bar is part of that band rather than a strip of
 * a different colour drawn across the top of it. Both follow the reader's theme.
 *
 * `withDrawer: false` drops the hamburger, because the landing page has no
 * sidebar to open; it links into the documentation instead.
 *
 * `withTheme: false` drops the day/night control, for a page that has no light
 * version to switch to. Nothing passes it today.
 */
export function masthead({
  base,
  version,
  site,
  withDrawer = true,
  docsUrl,
  onInk = false,
  withTheme = true,
}) {
  const iconButton = onInk ? ICON_BUTTON_INK : ICON_BUTTON

  const drawerButton = withDrawer
    ? `<button class="${iconButton} md:hidden" type="button" data-drawer-toggle aria-expanded="false" aria-controls="sidebar">
      <span class="in-[body.drawer-open]:hidden">${icon.menu}</span>
      <span class="hidden in-[body.drawer-open]:block">${icon.close}</span>
      <span class="sr-only">Toggle navigation</span>
    </button>`
    : ''

  // The label shortens rather than disappearing: on the landing page this is the
  // only way into the documentation that is not a call-to-action button.
  const docsLink = docsUrl
    ? `<a class="hidden rounded-md px-2.5 py-1.5 text-sm font-medium no-underline transition-colors sm:block ${
        onInk
          ? 'text-band-muted hover:bg-band-soft-strong hover:text-band-ink'
          : 'text-muted hover:bg-sunken hover:text-ink'
      }" href="${docsUrl}">Documentation</a>`
    : ''

  const shell = onInk
    ? 'bg-band [&.is-scrolled]:border-band-line [&.is-scrolled]:bg-band/85 [&.is-scrolled]:backdrop-blur-md'
    : '[&.is-scrolled]:border-line [&.is-scrolled]:bg-page/80 [&.is-scrolled]:backdrop-blur-md'

  return `<header class="sticky top-0 z-50 h-(--masthead-h) border-b border-transparent transition-[background-color,border-color,backdrop-filter] ${shell}" data-masthead>
  <div class="mx-auto flex h-full max-w-(--shell-max) items-center gap-1 px-3 sm:gap-2 sm:px-5">
    ${drawerButton}
    <a class="flex min-w-0 items-center gap-2.5 no-underline" href="${base}">
      ${icon.logo('masthead')}
      <span class="flex min-w-0 flex-col leading-none">
        <span class="truncate font-display text-[0.9375rem] font-bold tracking-tight ${onInk ? 'text-band-ink' : 'text-ink'}">Package Toolkit</span>
        <span class="mt-0.5 hidden text-[0.625rem] font-semibold uppercase tracking-[0.1em] sm:block ${onInk ? 'text-band-muted' : 'text-faint'}">for Laravel</span>
      </span>
    </a>

    <span class="ml-1 hidden shrink-0 rounded-full border px-2 py-0.5 font-mono text-[0.6875rem] sm:inline-block ${
      onInk ? 'border-band-line bg-band-soft text-band-muted' : 'border-line bg-sunken text-muted'
    }">v${escape(version)}</span>

    <div class="flex-1"></div>

    ${docsLink}

    <button class="flex items-center gap-2 rounded-md border py-1.5 pl-2.5 pr-2 transition-colors max-sm:size-9 max-sm:justify-center max-sm:border-0 max-sm:bg-transparent max-sm:p-0 ${
      onInk
        ? 'border-band-line bg-band-soft text-band-muted hover:border-band-line-strong hover:text-band-ink'
        : 'border-line bg-sunken text-muted hover:border-line-strong hover:text-ink'
    }" type="button" data-search-open>
      ${icon.search}
      <span class="hidden text-sm sm:block">Search</span>
      <kbd class="hidden rounded-xs border px-1.5 font-mono text-[0.6875rem] sm:block ${
        onInk ? 'border-band-line bg-band-soft' : 'border-line bg-surface text-faint'
      }">/</kbd>
    </button>

    ${
      withTheme
        ? `<button class="${iconButton}" type="button" data-theme-toggle>
      <span class="dark:hidden">${icon.moon}</span>
      <span class="hidden dark:block">${icon.sun}</span>
      <span class="sr-only">Toggle colour theme</span>
    </button>`
        : ''
    }

    <a class="${iconButton}" href="${site.repository}" rel="noopener noreferrer" target="_blank">
      ${icon.github}<span class="sr-only">GitHub repository</span>
    </a>
  </div>
</header>
`
}

export function searchDialog() {
  return `<div class="fixed inset-0 z-90 flex items-start justify-center px-4 pt-[10vh]" data-search-dialog hidden>
  <!-- search-backdrop and search-panel carry no styling: they are the hooks the
       stylesheet animates the dialog's entrance from, because a dialog shown by
       dropping the hidden attribute has no previous state to transition out of. -->
  <div class="search-backdrop absolute inset-0 bg-ink/40 backdrop-blur-sm" data-search-close></div>
  <div class="search-panel relative flex max-h-[75vh] w-full max-w-2xl flex-col overflow-hidden rounded-xl border border-line bg-surface shadow-lg" role="dialog" aria-modal="true" aria-label="Search documentation">
    <div class="flex items-center gap-3 border-b border-line px-4 text-faint">
      ${icon.search}
      <input class="min-w-0 flex-1 bg-transparent py-3.5 text-[0.9375rem] text-ink outline-none placeholder:text-faint" type="search" placeholder="Search the documentation…" data-search-input autocomplete="off" spellcheck="false" enterkeyhint="go">
      <button class="${ICON_BUTTON}" type="button" data-search-close>${icon.close}<span class="sr-only">Close search</span></button>
    </div>
    <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain p-2" data-search-results></div>
    <p class="hidden items-center gap-1.5 border-t border-line px-4 py-2 text-xs text-faint sm:flex [&_kbd]:rounded-xs [&_kbd]:border [&_kbd]:border-line [&_kbd]:bg-sunken [&_kbd]:px-1.5 [&_kbd]:font-mono">
      <kbd>↑</kbd><kbd>↓</kbd> to navigate · <kbd>↵</kbd> to open · <kbd>esc</kbd> to close
    </p>
  </div>
</div>
`
}

export function scripts(base) {
  return `<script>window.__DOCS_BASE__ = ${JSON.stringify(base)}</script>
<script src="${base}assets/docs.js" defer></script>
</body>
</html>
`
}
