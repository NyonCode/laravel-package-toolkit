/* Documentation site behaviour: theme, navigation drawer, on-this-page
   scrollspy, copy buttons and search. No dependencies, no build step — the file
   is served as written. */
;(function () {
  'use strict'

  var base = window.__DOCS_BASE__ || '/'
  var root = document.documentElement

  /* ----------------------------------------------------------------- theme */

  function systemTheme() {
    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
  }

  function currentTheme() {
    return root.dataset.theme || systemTheme()
  }

  document.querySelectorAll('[data-theme-toggle]').forEach(function (button) {
    button.addEventListener('click', function () {
      var next = currentTheme() === 'dark' ? 'light' : 'dark'
      root.dataset.theme = next
      try {
        localStorage.setItem('lpt-theme', next)
      } catch (error) {
        /* private mode — the choice just does not survive the tab */
      }
    })
  })

  /* ---------------------------------------------------------------- drawer */

  var body = document.body

  function closeDrawer() {
    body.classList.remove('drawer-open')
    document.querySelectorAll('[data-drawer-toggle]').forEach(function (button) {
      button.setAttribute('aria-expanded', 'false')
    })
    var backdrop = document.querySelector('.drawer-backdrop')
    if (backdrop) backdrop.hidden = true
  }

  function openDrawer() {
    body.classList.add('drawer-open')
    document.querySelectorAll('[data-drawer-toggle]').forEach(function (button) {
      button.setAttribute('aria-expanded', 'true')
    })
    var backdrop = document.querySelector('.drawer-backdrop')
    if (backdrop) backdrop.hidden = false
  }

  document.querySelectorAll('[data-drawer-toggle]').forEach(function (button) {
    button.addEventListener('click', function () {
      body.classList.contains('drawer-open') ? closeDrawer() : openDrawer()
    })
  })

  document.querySelectorAll('[data-drawer-close]').forEach(function (element) {
    element.addEventListener('click', closeDrawer)
  })

  // Following a link inside the drawer should not leave it hanging open on the
  // way to a same-page anchor.
  var sidebar = document.getElementById('sidebar')

  if (sidebar) {
    sidebar.addEventListener('click', function (event) {
      if (event.target.closest('a')) closeDrawer()
    })

    // Keep the sidebar looking at the same place across a navigation — the
    // reader is usually moving between adjacent pages.
    var key = 'lpt-sidebar-scroll'

    try {
      var stored = sessionStorage.getItem(key)
      if (stored) sidebar.scrollTop = Number(stored)
    } catch (error) {
      /* ignore */
    }

    var active = sidebar.querySelector('.nav-link.is-active')

    if (active) {
      var top = active.offsetTop
      var visible = sidebar.scrollTop + sidebar.clientHeight

      if (top < sidebar.scrollTop || top > visible - 60) {
        sidebar.scrollTop = Math.max(0, top - sidebar.clientHeight / 2)
      }
    }

    window.addEventListener('beforeunload', function () {
      try {
        sessionStorage.setItem(key, String(sidebar.scrollTop))
      } catch (error) {
        /* ignore */
      }
    })
  }

  /* ------------------------------------------------------ sidebar: groups */

  /* The group holding the current page ships open from the build. On top of
     that, whatever the reader opens is remembered, so a sidebar they widened
     stays widened as they move between pages. */
  var NAV_KEY = 'lpt-nav-open'
  var groups = Array.prototype.slice.call(document.querySelectorAll('[data-nav-group]'))
  var filterActive = false

  function storedOpenGroups() {
    try {
      var raw = sessionStorage.getItem(NAV_KEY)
      return raw ? JSON.parse(raw) : []
    } catch (error) {
      return []
    }
  }

  function rememberOpenGroups() {
    var open = groups
      .filter(function (group) {
        return group.open
      })
      .map(function (group) {
        return group.getAttribute('data-nav-group')
      })

    try {
      sessionStorage.setItem(NAV_KEY, JSON.stringify(open))
    } catch (error) {
      /* ignore */
    }
  }

  if (groups.length) {
    var remembered = storedOpenGroups()

    groups.forEach(function (group) {
      if (remembered.indexOf(group.getAttribute('data-nav-group')) !== -1) {
        group.open = true
      }

      group.addEventListener('toggle', function () {
        if (!filterActive) rememberOpenGroups()
      })
    })
  }

  /* ------------------------------------------------------ sidebar: filter */

  /* With 31 pages the fastest route to one is typing three letters of its name.
     This filters titles only; the dialog behind `/` searches the text of every
     page, which is a different and heavier job. */
  var filter = document.querySelector('[data-nav-filter]')
  var navEmpty = document.querySelector('[data-nav-empty]')

  if (filter) {
    var navLinks = Array.prototype.slice.call(document.querySelectorAll('.nav-link'))

    navLinks.forEach(function (link) {
      link.dataset.title = link.textContent
    })

    var highlight = function (link, query) {
      var title = link.dataset.title
      var at = query ? title.toLowerCase().indexOf(query) : -1

      if (at === -1) {
        link.textContent = title
        return
      }

      link.textContent = ''
      link.appendChild(document.createTextNode(title.slice(0, at)))
      var hit = document.createElement('mark')
      hit.textContent = title.slice(at, at + query.length)
      link.appendChild(hit)
      link.appendChild(document.createTextNode(title.slice(at + query.length)))
    }

    var applyFilter = function () {
      var query = filter.value.trim().toLowerCase()
      var remembered = storedOpenGroups()
      var hits = 0

      filterActive = query !== ''

      groups.forEach(function (group) {
        var groupHits = 0

        Array.prototype.forEach.call(group.querySelectorAll('.nav-link'), function (link) {
          var match = !query || link.dataset.title.toLowerCase().indexOf(query) !== -1
          link.parentNode.hidden = !match
          highlight(link, query)
          if (match) groupHits++
        })

        group.hidden = groupHits === 0
        hits += groupHits

        // While filtering, every group with a hit is open; the reader's own
        // choices come back the moment the box is cleared.
        group.open = query
          ? groupHits > 0
          : group.hasAttribute('data-current') ||
            remembered.indexOf(group.getAttribute('data-nav-group')) !== -1
      })

      if (navEmpty) navEmpty.hidden = hits !== 0
    }

    filter.addEventListener('input', applyFilter)

    filter.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') {
        filter.value = ''
        applyFilter()
        return
      }

      if (event.key === 'Enter') {
        var first = document.querySelector('.nav-group:not([hidden]) li:not([hidden]) > .nav-link')
        if (first) first.click()
      }
    })

    // Nothing matched by title? The text of every page is still searchable.
    var toSearch = document.querySelector('[data-nav-search]')

    if (toSearch) {
      toSearch.addEventListener('click', function () {
        var query = filter.value
        var opener = document.querySelector('[data-search-open]')
        if (opener) opener.click()
        var input = document.querySelector('[data-search-input]')
        if (input) {
          input.value = query
          input.dispatchEvent(new Event('input'))
        }
      })
    }
  }

  /* -------------------------------------------------------------- scrollspy */

  var tocLinks = Array.prototype.slice.call(document.querySelectorAll('.toc-item a'))

  if (tocLinks.length && 'IntersectionObserver' in window) {
    var byId = {}

    tocLinks.forEach(function (link) {
      byId[link.getAttribute('href').slice(1)] = link.parentElement
    })

    var headings = Object.keys(byId)
      .map(function (id) {
        return document.getElementById(id)
      })
      .filter(Boolean)

    var visible = new Set()

    var observer = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (entry) {
          entry.isIntersecting ? visible.add(entry.target.id) : visible.delete(entry.target.id)
        })

        // The topmost heading currently in the reading band wins; when nothing
        // is in it (a long section), keep the last one we passed.
        var first = headings.find(function (heading) {
          return visible.has(heading.id)
        })

        if (!first) return

        Object.keys(byId).forEach(function (id) {
          byId[id].classList.toggle('is-active', id === first.id)
        })
      },
      { rootMargin: '-72px 0px -70% 0px', threshold: 0 },
    )

    headings.forEach(function (heading) {
      observer.observe(heading)
    })
  }

  /* ------------------------------------------------------------------- copy */

  /* One clipboard path for both copy buttons: the async API where it is allowed,
     a hidden textarea and execCommand where it is not (a page served over plain
     http, which is how a contributor previews the site locally). */
  function writeClipboard(text, done) {
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(text).then(
        function () {
          done(true)
        },
        function () {
          done(false)
        },
      )
      return
    }

    var scratch = document.createElement('textarea')
    scratch.value = text
    scratch.setAttribute('readonly', '')
    scratch.style.cssText = 'position:fixed;top:-9999px'
    document.body.appendChild(scratch)
    scratch.select()

    try {
      done(document.execCommand('copy'))
    } catch (error) {
      done(false)
    }

    document.body.removeChild(scratch)
  }

  document.querySelectorAll('[data-copy]').forEach(function (button) {
    button.addEventListener('click', function () {
      var block = button.closest('.code-block')
      if (!block) return

      // Torchlight leaves the untouched source in a hidden textarea; the
      // offline fallback writes the same one, so both paths copy real code
      // rather than the rendered spans.
      var original = block.querySelector('textarea[data-torchlight-original]')
      var code = original
        ? original.value || original.textContent
        : (block.querySelector('code') || {}).textContent || ''

      var label = button.querySelector('[data-copy-label]')

      writeClipboard(code, function (ok) {
        button.classList.toggle('is-copied', ok)
        if (label) label.textContent = ok ? 'Copied' : 'Failed'
        setTimeout(function () {
          button.classList.remove('is-copied')
          if (label) label.textContent = 'Copy'
        }, 1600)
      })
    })
  })

  /* Anything carrying its own text — the landing page's install command. */
  document.querySelectorAll('[data-copy-text]').forEach(function (button) {
    button.addEventListener('click', function () {
      writeClipboard(button.getAttribute('data-copy-text') || '', function (ok) {
        button.classList.toggle('is-copied', ok)
        setTimeout(function () {
          button.classList.remove('is-copied')
        }, 1600)
      })
    })
  })

  /* -------------------------------------------------------------- masthead */

  /* The landing page's header sits on the dark hero with no ground of its own;
     once the hero has scrolled past, it needs one. */
  var masthead = document.querySelector('[data-masthead]')

  if (masthead) {
    var scrolled = null

    function syncMasthead() {
      var next = window.scrollY > 8
      if (next === scrolled) return
      scrolled = next
      masthead.classList.toggle('is-scrolled', next)
    }

    syncMasthead()
    window.addEventListener('scroll', syncMasthead, { passive: true })
  }

  /* ------------------------------------------------------- provider card */

  /* The landing page's hero card: the same service provider written by hand and
     declared, in one frame. The line count in the header is read off the tab
     that owns it, so it can never disagree with the code being shown. */
  var provider = document.querySelector('[data-provider]')

  if (provider) {
    var tabs = Array.prototype.slice.call(provider.querySelectorAll('[data-provider-tab]'))
    var counter = provider.querySelector('[data-provider-count]')
    var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches
    var touched = false

    function show(name, focus) {
      tabs.forEach(function (tab) {
        var isCurrent = tab.getAttribute('data-provider-tab') === name
        tab.setAttribute('aria-selected', isCurrent ? 'true' : 'false')
        tab.setAttribute('tabindex', isCurrent ? '0' : '-1')

        if (isCurrent) {
          if (counter) counter.textContent = tab.getAttribute('data-provider-lines') || ''
          if (focus) tab.focus()
        }
      })

      provider.querySelectorAll('[data-provider-pane]').forEach(function (pane) {
        pane.hidden = pane.getAttribute('data-provider-pane') !== name
      })

      provider.setAttribute('data-state', name)
    }

    tabs.forEach(function (tab, index) {
      tab.addEventListener('click', function () {
        touched = true
        show(tab.getAttribute('data-provider-tab'), false)
      })

      tab.addEventListener('keydown', function (event) {
        var step = event.key === 'ArrowRight' ? 1 : event.key === 'ArrowLeft' ? -1 : 0
        if (!step) return

        event.preventDefault()
        touched = true
        var next = tabs[(index + step + tabs.length) % tabs.length]
        show(next.getAttribute('data-provider-tab'), true)
      })
    })

    show('hand', false)

    // The collapse is the argument, so it plays once, unprompted — but a reader
    // who asked for less motion, or who has already picked a tab, is left alone.
    if (reduced) {
      show('toolkit', false)
    } else {
      setTimeout(function () {
        if (!touched) show('toolkit', false)
      }, 2200)
    }
  }

  /* ----------------------------------------------------------------- search */

  var dialog = document.querySelector('[data-search-dialog]')

  if (!dialog) return

  var input = dialog.querySelector('[data-search-input]')
  var results = dialog.querySelector('[data-search-results]')
  var index = null
  var loading = null
  var activeIndex = 0

  function loadIndex() {
    if (index) return Promise.resolve(index)
    if (loading) return loading

    loading = fetch(base + 'assets/search-index.json')
      .then(function (response) {
        return response.json()
      })
      .then(function (data) {
        index = data
        return index
      })
      .catch(function () {
        index = []
        return index
      })

    return loading
  }

  function openSearch() {
    dialog.hidden = false
    body.style.overflow = 'hidden'
    loadIndex().then(function () {
      if (input.value.trim()) render(input.value)
    })
    input.focus()
    input.select()
  }

  function closeSearch() {
    dialog.hidden = true
    body.style.overflow = ''
  }

  document.querySelectorAll('[data-search-open]').forEach(function (button) {
    button.addEventListener('click', openSearch)
  })

  document.querySelectorAll('[data-search-close]').forEach(function (element) {
    element.addEventListener('click', closeSearch)
  })

  function escapeHtml(value) {
    return value.replace(/[&<>"]/g, function (character) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[character]
    })
  }

  function score(entry, terms) {
    var title = entry.t.toLowerCase()
    var section = entry.s.toLowerCase()
    var headings = entry.h
      .map(function (heading) {
        return heading.t
      })
      .join(' ')
      .toLowerCase()
    var body = entry.b.toLowerCase()

    var total = 0

    for (var i = 0; i < terms.length; i++) {
      var term = terms[i]
      var hit = 0

      if (title === term) hit += 120
      else if (title.indexOf(term) !== -1) hit += 60
      if (headings.indexOf(term) !== -1) hit += 24
      if (section.indexOf(term) !== -1) hit += 8
      if (body.indexOf(term) !== -1) hit += 10

      // Every term has to appear somewhere, so a two-word query narrows
      // instead of widening.
      if (hit === 0) return 0

      total += hit
    }

    return total
  }

  function snippet(entry, terms) {
    var body = entry.b
    var lower = body.toLowerCase()
    var at = -1

    for (var i = 0; i < terms.length && at === -1; i++) {
      at = lower.indexOf(terms[i])
    }

    var start = at === -1 ? 0 : Math.max(0, at - 60)
    var text = (start > 0 ? '…' : '') + body.slice(start, start + 190) + '…'
    var html = escapeHtml(text)

    terms.forEach(function (term) {
      html = html.replace(
        new RegExp('(' + term.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi'),
        '<mark>$1</mark>',
      )
    })

    return html
  }

  function render(query) {
    var terms = query
      .toLowerCase()
      .split(/\s+/)
      .filter(function (term) {
        return term.length > 1
      })

    if (!terms.length || !index) {
      results.innerHTML = '<p class="search-empty">Type at least two characters.</p>'
      return
    }

    var matches = index
      .map(function (entry) {
        return { entry: entry, score: score(entry, terms) }
      })
      .filter(function (match) {
        return match.score > 0
      })
      .sort(function (a, b) {
        return b.score - a.score
      })
      .slice(0, 12)

    if (!matches.length) {
      results.innerHTML = '<p class="search-empty">No matches for “' + escapeHtml(query) + '”.</p>'
      return
    }

    activeIndex = 0
    results.innerHTML = matches
      .map(function (match, position) {
        return (
          '<a class="search-result' +
          (position === 0 ? ' is-active' : '') +
          '" href="' +
          match.entry.u +
          '">' +
          '<span class="search-result__section">' +
          escapeHtml(match.entry.s) +
          '</span>' +
          '<span class="search-result__title">' +
          escapeHtml(match.entry.t) +
          '</span>' +
          '<span class="search-result__snippet">' +
          snippet(match.entry, terms) +
          '</span></a>'
        )
      })
      .join('')
  }

  var debounce

  input.addEventListener('input', function () {
    clearTimeout(debounce)
    debounce = setTimeout(function () {
      render(input.value)
    }, 90)
  })

  function move(delta) {
    var items = results.querySelectorAll('.search-result')
    if (!items.length) return

    items[activeIndex].classList.remove('is-active')
    activeIndex = (activeIndex + delta + items.length) % items.length
    items[activeIndex].classList.add('is-active')
    items[activeIndex].scrollIntoView({ block: 'nearest' })
  }

  dialog.addEventListener('keydown', function (event) {
    if (event.key === 'ArrowDown') {
      event.preventDefault()
      move(1)
    } else if (event.key === 'ArrowUp') {
      event.preventDefault()
      move(-1)
    } else if (event.key === 'Enter') {
      var active = results.querySelector('.search-result.is-active')
      if (active) {
        event.preventDefault()
        window.location.href = active.getAttribute('href')
      }
    }
  })

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && !dialog.hidden) {
      closeSearch()
      return
    }

    var typing = /^(INPUT|TEXTAREA|SELECT)$/.test(event.target.tagName) || event.target.isContentEditable

    if (!typing && (event.key === '/' || ((event.metaKey || event.ctrlKey) && event.key === 'k'))) {
      event.preventDefault()
      openSearch()
    }
  })

  // Warm the index on the first hint of intent, so the first query is instant.
  document.addEventListener('pointerdown', function warm() {
    loadIndex()
    document.removeEventListener('pointerdown', warm)
  })
})()
