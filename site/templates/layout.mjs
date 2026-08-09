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

import {
    documentHead,
    escape,
    icon,
    masthead,
    scripts,
    searchDialog,
} from './chrome.mjs';
import { markdownPath } from '../llms.mjs';

/**
 * A documentation link in the sidebar.
 *
 * `nav-link` carries no styling — it is the hook `docs.js` filters and highlights
 * by — and `is-active` is likewise set from the build and read by the script, so
 * the state it stands for is styled here with a variant rather than in a
 * stylesheet.
 */
const NAV_LINK =
    'nav-link relative block rounded-md py-1.5 pl-4 pr-3 text-sm text-muted no-underline ' +
    'transition-colors hover:bg-sunken hover:text-ink pointer-coarse:py-2.5 ' +
    '[&_mark]:bg-transparent [&_mark]:font-semibold [&_mark]:text-accent ' +
    '[&.is-active]:font-medium [&.is-active]:text-accent ' +
    '[&.is-active]:before:absolute [&.is-active]:before:inset-y-1.5 [&.is-active]:before:-left-px ' +
    '[&.is-active]:before:w-0.5 [&.is-active]:before:rounded-full [&.is-active]:before:bg-accent ' +
    "[&.is-active]:before:content-['']";

const TOC_LINK =
    'block rounded-md py-1 text-[0.8125rem] leading-snug text-muted no-underline transition-colors ' +
    'hover:text-ink pointer-coarse:py-2';

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
        .map(section => {
            const isCurrent = section.pages.some(
                page => page.url === current.url,
            );

            const items = section.pages
                .map(page => {
                    const active = page.url === current.url;
                    return `<li><a class="${NAV_LINK}${active ? ' is-active' : ''}" href="${base}${page.url}${page.url ? '/' : ''}"${
                        active ? ' aria-current="page"' : ''
                    }>${escape(page.title)}</a></li>`;
                })
                .join('');

            return `<details class="group border-b border-line/70 py-1 last:border-0" data-nav-group="${escape(section.title)}"${isCurrent ? ' open data-current' : ''}>
        <summary class="flex cursor-pointer list-none items-center gap-1.5 rounded-md px-2 py-2 font-display text-[0.6875rem] font-semibold uppercase tracking-[0.08em] text-faint transition-colors hover:text-ink group-data-current:text-ink [&::-webkit-details-marker]:hidden">
          <span class="text-line-strong transition-transform group-open:rotate-90 group-data-current:text-accent">${icon.chevron}</span>
          ${escape(section.title)}
        </summary>
        <ul class="ml-2 border-l border-line pb-1">${items}</ul>
      </details>`;
        })
        .join('');

    return `<div class="sticky top-0 z-10 bg-page/95 pb-2 pt-1 backdrop-blur-sm">
      <input type="search" class="w-full rounded-md border border-line bg-sunken px-3 py-1.5 text-sm text-ink outline-none transition-colors placeholder:text-faint focus:border-line-strong focus:bg-surface" data-nav-filter placeholder="Filter pages" aria-label="Filter documentation pages" autocomplete="off" spellcheck="false">
    </div>
    ${groups}
    <p class="px-2 py-3 text-sm text-muted" data-nav-empty hidden>No page matches. <button class="text-accent underline underline-offset-2" type="button" data-nav-search>Search the text instead</button></p>`;
}

function tocItems(headings) {
    return headings
        .map(
            heading =>
                `<li class="toc-item [&.is-active>a]:font-medium [&.is-active>a]:text-accent"><a class="${TOC_LINK}${heading.level === 3 ? ' pl-3 text-faint' : ''}" href="#${heading.id}">${escape(heading.title)}</a></li>`,
        )
        .join('');
}

function railMarkup(headings) {
    if (headings.length < 2) {
        return '';
    }

    return `<nav class="border-l border-line pl-4" aria-labelledby="toc-title">
        <p class="mb-2 font-display text-[0.6875rem] font-semibold uppercase tracking-[0.08em] text-faint" id="toc-title">On this page</p>
        <ul>${tocItems(headings)}</ul>
      </nav>`;
}

/** The same headings, for the widths where the rail is not rendered. */
function inlineTocMarkup(headings) {
    if (headings.length < 2) {
        return '';
    }

    return `<details class="group mb-8 rounded-lg border border-line bg-sunken px-4 rail:hidden">
      <summary class="flex cursor-pointer list-none items-center gap-2 py-3 font-display text-sm font-semibold text-ink [&::-webkit-details-marker]:hidden">
        <span class="text-line-strong transition-transform group-open:rotate-90">${icon.chevron}</span>
        On this page
        <span class="rounded-sm bg-surface px-1.5 py-0.5 font-mono text-[0.6875rem] font-normal text-faint">${headings.length}</span>
      </summary>
      <ul class="border-t border-line py-2">${tocItems(headings)}</ul>
    </details>`;
}

function pagerMarkup(previous, next, base) {
    if (!previous && !next) {
        return '';
    }

    const link = (page, direction) =>
        page
            ? `<a class="group flex flex-col gap-1 rounded-lg border border-line p-4 no-underline transition-colors hover:border-line-strong hover:bg-sunken${
                  direction === 'next' ? ' text-right' : ''
              }" href="${base}${page.url}${page.url ? '/' : ''}">
          <span class="flex items-center gap-1.5 text-xs text-faint${direction === 'next' ? ' justify-end' : ''}">${
              direction === 'prev'
                  ? `${icon.arrowLeft} Previous`
                  : `Next ${icon.arrowRight}`
          }</span>
          <span class="font-display font-semibold text-ink transition-colors group-hover:text-accent">${escape(page.title)}</span>
        </a>`
            : '<span></span>';

    return `<nav class="mt-14 grid gap-3 sm:grid-cols-2" aria-label="Pagination">${link(previous, 'prev')}${link(next, 'next')}</nav>`;
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
    const title = `${page.title} — ${site.title}`;
    const description = page.description || site.description;
    const canonical = `${site.origin}${base}${page.url}${page.url ? '/' : ''}`;

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
<div class="drawer-backdrop fixed inset-0 z-30 bg-ink/40 backdrop-blur-xs md:hidden" data-drawer-close hidden></div>

<div class="mx-auto max-w-(--shell-max) md:grid md:grid-cols-[var(--sidebar-w)_minmax(0,1fr)] rail:grid-cols-[var(--sidebar-w)_minmax(0,1fr)_var(--rail-w)]">
  <!-- The drawer stops at the masthead rather than covering it: the button that
       closes it is up there, and a drawer that hides its own handle is a trap. -->
  <aside class="fixed bottom-0 left-0 top-(--masthead-h) z-40 w-(--sidebar-w) -translate-x-full overflow-y-auto overscroll-contain border-r border-line bg-page px-3 pb-10 pt-4 transition-transform duration-200 ease-out in-[body.drawer-open]:translate-x-0 md:sticky md:bottom-auto md:top-(--masthead-h) md:z-auto md:h-[calc(100dvh-var(--masthead-h))] md:translate-x-0" id="sidebar">
    <nav aria-label="Documentation">
      ${sidebarMarkup(sections, page, base)}
    </nav>
  </aside>

  <main class="min-w-0 px-5 pb-16 pt-8 sm:px-8 lg:px-12" id="content">
    <nav class="mb-6 flex items-center justify-between gap-4 text-xs text-faint" aria-label="Breadcrumb">
      <span class="flex min-w-0 items-center gap-1.5 truncate font-mono">
        <a class="text-faint no-underline hover:text-ink" href="${base}">docs</a>
        <span class="text-line-strong">/</span>
        <span>${escape(page.section.toLowerCase())}</span>
        <span class="text-line-strong">/</span>
        <span class="text-muted">${escape(page.title.toLowerCase())}</span>
      </span>
      <a class="hidden shrink-0 text-faint no-underline hover:text-accent sm:block" href="${editUrl}" rel="noopener noreferrer" target="_blank">edit this page →</a>
    </nav>

    <header class="mb-10 max-w-(--content-max)">
      <h1 class="font-display text-3xl font-extrabold tracking-tight text-ink sm:text-4xl">${escape(page.title)}</h1>
      ${page.description ? `<p class="mt-3 text-lg leading-relaxed text-muted">${escape(page.description)}</p>` : ''}
    </header>

    <div class="max-w-(--content-max)">
      ${inlineTocMarkup(headings)}

      <article class="prose">
        ${content}
      </article>

      ${pagerMarkup(previous, next, base)}

      <footer class="mt-12 flex flex-wrap items-center justify-between gap-3 border-t border-line pt-6 text-xs text-faint">
        <a class="text-faint no-underline hover:text-ink" href="${base}">Laravel Package Toolkit</a>
        <span>MIT licensed · © NyonCode</span>
      </footer>
    </div>
  </main>

  <div class="hidden rail:block">
    <div class="sticky top-[calc(var(--masthead-h)+2rem)] max-h-[calc(100dvh-var(--masthead-h)-4rem)] overflow-y-auto pb-10 pr-5">
      ${railMarkup(headings)}
    </div>
  </div>
</div>

${searchDialog()}
${scripts(base)}`;
}
