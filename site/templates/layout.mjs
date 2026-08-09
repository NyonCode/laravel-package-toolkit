/**
 * The documentation page shell.
 *
 * Three columns on a wide screen — navigation, prose, on-this-page — collapsing
 * to two at portrait-tablet width and to a single column with an off-canvas
 * drawer on a phone. Where the on-this-page rail does not fit, the same headings
 * are rendered as a collapsed `<details>` above the prose, so a phone reader is
 * not the only one without a way to jump around a long reference page.
 *
 * The head, masthead and search dialog come from `chrome.mjs`, shared with the
 * landing page.
 */

import { documentHead, escape, icon, masthead, scripts, searchDialog } from './chrome.mjs'
import { markdownPath } from '../llms.mjs'

/**
 * The sidebar.
 *
 * Each group is a native `<details>`, so it collapses with the keyboard and
 * without JavaScript, and the group holding the current page ships open. That
 * turns a 31-row list into an 8-row map plus the section you are reading —
 * `docs.js` then remembers whatever the reader opens on top of that, and the
 * filter above reaches anything without opening a single group.
 */
function sidebarMarkup(sections, current, base) {
  const groups = sections
    .map((section) => {
      const isCurrent = section.pages.some((page) => page.url === current.url)

      const items = section.pages
        .map((page) => {
          const active = page.url === current.url
          return `<li><a class="nav-link${active ? ' is-active' : ''}" href="${base}${page.url}${page.url ? '/' : ''}"${
            active ? ' aria-current="page"' : ''
          }>${escape(page.title)}</a></li>`
        })
        .join('')

      return `<details class="nav-group" data-nav-group="${escape(section.title)}"${isCurrent ? ' open data-current' : ''}>
        <summary class="nav-group__title">${escape(section.title)}</summary>
        <ul class="nav-list">${items}</ul>
      </details>`
    })
    .join('')

  return `<div class="nav-filter">
      <input type="search" class="nav-filter__input" data-nav-filter placeholder="Filter pages" aria-label="Filter documentation pages" autocomplete="off" spellcheck="false">
    </div>
    ${groups}
    <p class="nav-empty" data-nav-empty hidden>No page matches. <button type="button" data-nav-search>Search the text instead</button></p>`
}

function tocItems(headings) {
  return headings
    .map(
      (heading) =>
        `<li class="toc-item toc-item--h${heading.level}"><a href="#${heading.id}">${escape(heading.title)}</a></li>`,
    )
    .join('')
}

function railMarkup(headings) {
  if (headings.length < 2) {
    return ''
  }

  return `<nav class="toc" aria-labelledby="toc-title">
        <p class="toc__title" id="toc-title">On this page</p>
        <ul class="toc__list">${tocItems(headings)}</ul>
      </nav>`
}

/** The same headings, for the widths where the rail is not rendered. */
function inlineTocMarkup(headings) {
  if (headings.length < 2) {
    return ''
  }

  return `<details class="toc-inline">
      <summary class="toc-inline__summary">On this page<span class="toc-inline__count">${headings.length}</span></summary>
      <ul class="toc-inline__list">${tocItems(headings)}</ul>
    </details>`
}

function pagerMarkup(previous, next, base) {
  if (!previous && !next) {
    return ''
  }

  const link = (page, direction) =>
    page
      ? `<a class="pager__link pager__link--${direction}" href="${base}${page.url}${page.url ? '/' : ''}">
          <span class="pager__direction">${direction === 'prev' ? `${icon.arrowLeft} Previous` : `Next ${icon.arrowRight}`}</span>
          <span class="pager__title">${escape(page.title)}</span>
        </a>`
      : '<span></span>'

  return `<nav class="pager" aria-label="Pagination">${link(previous, 'prev')}${link(next, 'next')}</nav>`
}

export function layout({
  page,
  content,
  headings,
  sections,
  previous,
  next,
  base,
  site,
  version,
  editUrl,
}) {
  const title = `${page.title} — ${site.title}`
  const description = page.description || site.description
  const canonical = `${site.origin}${base}${page.url}${page.url ? '/' : ''}`

  return `${documentHead({
    title,
    description,
    canonical,
    base,
    site,
    pageId: page.url || 'home',
    // The 404 page is assembled here rather than loaded from `docs/`, so it has
    // no `file` and no Markdown twin to point at.
    markdown: page.file ? `${base}${markdownPath(page)}` : null,
  })}
${masthead({ base, version, site })}
<div class="drawer-backdrop" data-drawer-close hidden></div>

<div class="shell">
  <aside class="sidebar" id="sidebar">
    <nav class="sidebar__nav" aria-label="Documentation">
      ${sidebarMarkup(sections, page, base)}
    </nav>
  </aside>

  <main class="content" id="content">
    <nav class="crumbs" aria-label="Breadcrumb">
      <span class="crumbs__path">
        <a href="${base}">docs</a>
        <span class="crumbs__sep">/</span>
        <span class="crumbs__here">${escape(page.section.toLowerCase())}</span>
        <span class="crumbs__sep">/</span>
        <span class="crumbs__here">${escape(page.title.toLowerCase())}</span>
      </span>
      <a class="crumbs__edit" href="${editUrl}" rel="noopener noreferrer" target="_blank">edit this page →</a>
    </nav>

    <header class="page-head">
      <h1>${escape(page.title)}</h1>
      ${page.description ? `<p class="page-head__lead">${escape(page.description)}</p>` : ''}
    </header>

    ${inlineTocMarkup(headings)}

    <article class="prose">
      ${content}
    </article>

    ${pagerMarkup(previous, next, base)}

    <footer class="page-footer">
      <a href="${base}">Laravel Package Toolkit</a>
      <span>MIT licensed · © NyonCode</span>
    </footer>
  </main>

  <div class="rail">
    ${railMarkup(headings)}
  </div>
</div>

${searchDialog()}
${scripts(base)}`
}
