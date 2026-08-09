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

  /* ------------------------------------------------------- builder widget */

  /* The vocabulary section is a live provider: pressing a chip shows that
     builder's line, releasing it hides it. Every line was rendered and
     highlighted at build time, so nothing here parses PHP — it toggles rows and
     moves the closing semicolon onto whichever line ends up last. */
  var widget = document.querySelector('[data-builder-widget]')

  if (widget) {
    var chips = Array.prototype.slice.call(widget.querySelectorAll('[data-builder]'))
    var lines = Array.prototype.slice.call(widget.querySelectorAll('.lp-builder__out .line'))
    var countOut = widget.querySelector('[data-builder-count]')
    var docsOut = widget.querySelector('[data-builder-docs]')
    var copyOut = widget.querySelector('[data-builder-copy]')
    var quiet = window.matchMedia('(prefers-reduced-motion: reduce)').matches

    // Match each rendered line to the chip that owns it. The call text is the
    // key, so a chip and its line cannot drift apart.
    var owned = {}

    chips.forEach(function (chip) {
      var call = chip.getAttribute('data-builder')
      var head = call.split('(')[0]

      for (var i = 0; i < lines.length; i++) {
        if (lines[i].textContent.indexOf(head + '(') !== -1 && !lines[i].dataset.owner) {
          lines[i].dataset.owner = call
          owned[call] = lines[i]
          break
        }
      }
    })

    var semicolon = document.createElement('span')
    semicolon.className = 'tok-pun'
    semicolon.textContent = ';'

    function sync(changed) {
      var visible = []

      chips.forEach(function (chip) {
        var on = chip.getAttribute('aria-pressed') === 'true'
        var line = owned[chip.getAttribute('data-builder')]
        if (!line) return

        line.hidden = !on
        if (on) visible.push(chip)
      })

      // The chain always ends in a semicolon, and only on its last line.
      var tail = visible.length
        ? owned[visible[visible.length - 1].getAttribute('data-builder')]
        : widget.querySelector('.lp-builder__out .line[data-name-line]')

      if (tail) tail.appendChild(semicolon)

      var shown = lines.filter(function (line) {
        return !line.hidden
      })

      if (countOut) countOut.textContent = String(shown.length)

      if (copyOut) {
        copyOut.setAttribute(
          'data-copy-text',
          shown
            .map(function (line) {
              return line.textContent.replace(/\u200b/g, '')
            })
            .join('\n'),
        )
      }

      if (docsOut) {
        if (changed) {
          docsOut.hidden = false
          docsOut.href = changed.getAttribute('data-builder-url')
          docsOut.textContent = changed.getAttribute('data-builder-label') + ' →'
        } else if (!visible.length) {
          docsOut.hidden = true
        }
      }
    }

    chips.forEach(function (chip) {
      chip.addEventListener('click', function () {
        var on = chip.getAttribute('aria-pressed') !== 'true'
        chip.setAttribute('aria-pressed', on ? 'true' : 'false')

        var line = owned[chip.getAttribute('data-builder')]

        if (on && line && !quiet) {
          line.classList.remove('is-new')
          void line.offsetWidth
          line.classList.add('is-new')
        }

        sync(on ? chip : null)
      })
    })

    // The copy button carries its text on data-copy-text, which the generic
    // clipboard handler above already knows how to read — but it is wired after
    // that handler ran, so it gets its own listener.
    if (copyOut) {
      copyOut.addEventListener('click', function () {
        writeClipboard(copyOut.getAttribute('data-copy-text') || '', function (ok) {
          copyOut.classList.toggle('is-copied', ok)
          copyOut.textContent = ok ? 'Copied' : 'Copy provider'
          setTimeout(function () {
            copyOut.classList.remove('is-copied')
            copyOut.textContent = 'Copy provider'
          }, 1600)
        })
      })
    }

    sync(null)
  }

  /* --------------------------------------------------------- scroll reveal */

  /* Sections arrive as they are reached. Driven by scroll position rather than an
     IntersectionObserver on purpose: an observer only fires for elements that
     actually intersect, so jumping straight to the foot of the page — End, an
     anchor, a restored scroll position — left everything it skipped paused at
     zero opacity, which is content permanently invisible. This checks position
     instead, so anything at or above the fold is revealed whether it was
     scrolled past or jumped over. */
  var pending = Array.prototype.slice.call(document.querySelectorAll('[data-reveal]'))

  if (pending.length) {
    var scheduled = false

    function sweep() {
      scheduled = false
      var edge = window.innerHeight * 0.92

      pending = pending.filter(function (target) {
        if (target.getBoundingClientRect().top > edge) return true
        target.classList.add('is-revealed')
        return false
      })

      if (!pending.length) {
        window.removeEventListener('scroll', queue)
        window.removeEventListener('resize', queue)
      }
    }

    function queue() {
      if (scheduled) return
      scheduled = true
      window.requestAnimationFrame(sweep)
    }

    window.addEventListener('scroll', queue, { passive: true })
    window.addEventListener('resize', queue)
    sweep()
  }

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

    // The counter counts rather than cuts: 54 to 19 is the whole argument, and a
    // number that lands in one frame is a number a reader does not register.
    var ticking = null

    function tick(to) {
      if (!counter) return

      var from = parseInt(counter.textContent, 10)
      window.clearInterval(ticking)

      if (reduced || isNaN(from) || from === to) {
        counter.textContent = String(to)
        return
      }

      var step = from > to ? -1 : 1
      var span = Math.abs(to - from)
      var every = Math.max(12, Math.round(360 / span))

      ticking = window.setInterval(function () {
        from += step
        counter.textContent = String(from)
        if (from === to) window.clearInterval(ticking)
      }, every)
    }

    function show(name, focus, animate) {
      tabs.forEach(function (tab) {
        var isCurrent = tab.getAttribute('data-provider-tab') === name
        tab.setAttribute('aria-selected', isCurrent ? 'true' : 'false')
        tab.setAttribute('tabindex', isCurrent ? '0' : '-1')

        if (isCurrent) {
          tick(parseInt(tab.getAttribute('data-provider-lines'), 10))
          if (focus) tab.focus()
        }
      })

      var panes = Array.prototype.slice.call(
        provider.querySelectorAll('[data-provider-pane]'),
      )

      function settle() {
        panes.forEach(function (pane) {
          var isCurrent = pane.getAttribute('data-provider-pane') === name
          pane.hidden = !isCurrent
          pane.classList.remove('is-leaving', 'is-entering')

          if (isCurrent && animate && !reduced) {
            // Restarting a CSS animation needs the class off, a reflow, and the
            // class on again.
            void pane.offsetWidth
            pane.classList.add('is-entering')
          }
        })

        provider.setAttribute('data-state', name)
      }

      var leaving = panes.filter(function (pane) {
        return !pane.hidden && pane.getAttribute('data-provider-pane') !== name
      })[0]

      if (animate && !reduced && leaving) {
        leaving.classList.add('is-leaving')
        window.setTimeout(settle, 200)
        return
      }

      settle()
    }

    // One custom property per line, counted from the top of each pane: a single
    // running index across both panes put the declaration's first line 54 steps
    // deep, so its stagger began over a second after the pane appeared.
    provider.querySelectorAll('[data-provider-pane]').forEach(function (pane) {
      var lines = pane.querySelectorAll('.line')

      lines.forEach(function (line, index) {
        line.style.setProperty('--i', String(index))
      })

      // The step is per pane and the total is capped: at a flat 20ms the
      // hand-written provider's 54 lines took a full second to arrive, which
      // reads as the page being slow rather than as anything landing.
      var step = Math.max(6, Math.min(22, Math.round(380 / Math.max(lines.length, 1))))
      pane.style.setProperty('--step-ms', step + 'ms')
    })

    // The caret belongs to the declaration, so it goes on its last line.
    var lastLine = provider.querySelector(
      '[data-provider-pane="toolkit"] .line:last-of-type',
    )

    if (lastLine) {
      var caret = document.createElement('span')
      caret.className = 'provider__caret'
      caret.setAttribute('aria-hidden', 'true')
      lastLine.appendChild(caret)
    }

    tabs.forEach(function (tab, index) {
      tab.addEventListener('click', function () {
        touched = true
        show(tab.getAttribute('data-provider-tab'), false, true)
      })

      tab.addEventListener('keydown', function (event) {
        var step = event.key === 'ArrowRight' ? 1 : event.key === 'ArrowLeft' ? -1 : 0
        if (!step) return

        event.preventDefault()
        touched = true
        var next = tabs[(index + step + tabs.length) % tabs.length]
        show(next.getAttribute('data-provider-tab'), true, true)
      })
    })

    show('hand', false, false)

    // The collapse is the argument, so it plays once, unprompted — but a reader
    // who asked for less motion, or who has already picked a tab, is left alone.
    if (reduced) {
      show('toolkit', false, false)
    } else {
      setTimeout(function () {
        if (!touched) show('toolkit', false, true)
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
