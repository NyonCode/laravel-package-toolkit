/**
 * The landing page.
 *
 * Documentation pages are a reading surface; this one has a single job — show a
 * package author that the provider they are about to write by hand is a
 * declaration instead. So the hero is not a statement about the library, it is
 * the library's argument in its own material: the same service provider twice,
 * once written out and once declared, in one frame, with the line counts taken
 * from the snippets below rather than typed in by hand.
 *
 * `renderCode` is injected by the build so these blocks are highlighted by
 * whichever path the build is using (Torchlight or the offline fallback), and
 * `codeBlock` frames them exactly the way a fenced block in the documentation is
 * framed.
 */

import {
    codeBlock,
    documentHead,
    escape,
    icon,
    masthead,
    scripts,
    searchDialog,
} from './chrome.mjs';

const BY_HAND = `class BlogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/blog.php', 'blog');
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'blog');
        $this->loadTranslationsFrom(__DIR__ . '/../lang', 'blog');
        $this->loadJsonTranslationsFrom(__DIR__ . '/../lang');

        Blade::componentNamespace('Vendor\\\\Blog\\\\View\\\\Components', 'blog');

        require __DIR__ . '/../routes/channels.php';

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            Commands\\PruneDraftsCommand::class,
            Commands\\ReindexCommand::class,
        ]);

        $this->publishes([
            __DIR__ . '/../config/blog.php' => config_path('blog.php'),
        ], 'blog-config');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'blog-migrations');

        $this->publishes([
            __DIR__ . '/../database/seeders/BlogSeeder.php' => database_path('seeders/BlogSeeder.php'),
        ], 'blog-seeders');

        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/blog'),
        ], 'blog-views');

        $this->publishes([
            __DIR__ . '/../lang' => lang_path('vendor/blog'),
        ], 'blog-translations');

        $this->publishes([
            __DIR__ . '/../resources/dist' => public_path('vendor/blog'),
        ], 'blog-assets');
    }
}`;

const WITH_TOOLKIT = `class BlogServiceProvider extends PackageServiceProvider
{
    public function configure(Packager $packager): void
    {
        $packager
            ->name('Blog')
            ->hasConfig()
            ->hasRoutes(['web.php', 'api.php'])
            ->hasBroadcastChannels(['channels.php'])
            ->hasMigrations()
            ->hasSeeders()
            ->hasTranslations()
            ->hasViews()
            ->hasComponentNamespace('Vendor\\\\Blog\\\\View\\\\Components')
            ->hasCommands()
            ->hasAssets()
            ->hasQuickInstall();
    }
}`;

/**
 * The vocabulary, as a live chain.
 *
 * Every entry is one line of a real provider: the label a reader recognises, the
 * page that documents it, and the call itself — with a representative argument
 * where the builder needs one, because a chip that produces `->hasEvents()` is
 * teaching code that does not run.
 *
 * The whole chain is rendered server-side and highlighted by the same path as
 * every other code block; toggling a chip only shows or hides a line. That is
 * what keeps this honest: there is no client-side tokenizer inventing colours,
 * and what a reader copies is what the highlighter was given.
 */
const BUILDERS = [
    ['hasConfig()', 'config', '->hasConfig()'],
    ['hasRoutes()', 'routes', "->hasRoutes(['web.php', 'api.php'])"],
    [
        'hasBroadcastChannels()',
        'broadcast-channels',
        "->hasBroadcastChannels(['channels.php'])",
    ],
    ['hasMigrations()', 'migrations', '->hasMigrations()'],
    ['hasSeeders()', 'seeders', '->hasSeeders()'],
    ['hasFactories()', 'factories', '->hasFactories()'],
    ['hasTranslations()', 'translations', '->hasTranslations()'],
    ['hasViews()', 'views', '->hasViews()'],
    [
        'hasComponents()',
        'view-components',
        "->hasComponents(['alert' => Alert::class])",
    ],
    [
        'hasComponentNamespace()',
        'view-components',
        "->hasComponentNamespace('Vendor\\\\Blog\\\\View\\\\Components')",
    ],
    [
        'hasViewComposer()',
        'view-composers',
        "->hasViewComposer('blog::sidebar', SidebarComposer::class)",
    ],
    [
        'hasSharedDataForAllViews()',
        'view-composers',
        "->hasSharedDataForAllViews(['brand' => 'Blog'])",
    ],
    ['hasAssets()', 'assets', '->hasAssets()'],
    ['hasViteAssets()', 'assets', "->hasViteAssets(['resources/js/blog.js'])"],
    [
        'hasAssetFallback()',
        'assets',
        "->hasAssetFallback(fn ($file) => route('blog.asset', $file))",
    ],
    [
        'hasMiddlewareAliases()',
        'middleware',
        "->hasMiddlewareAliases(['author' => EnsureAuthor::class])",
    ],
    [
        'hasMiddlewareGroups()',
        'middleware',
        "->hasMiddlewareGroups(['web' => [TrackReads::class]])",
    ],
    [
        'hasMiddlewareGlobals()',
        'middleware',
        '->hasMiddlewareGlobals([TrackReads::class])',
    ],
    [
        'hasEvents()',
        'events',
        '->hasEvents([Published::class => Notify::class])',
    ],
    ['hasSubscribers()', 'events', '->hasSubscribers([BlogSubscriber::class])'],
    ['hasCommands()', 'commands', '->hasCommands()'],
    [
        'hasOptimizeCommands()',
        'optimize',
        "->hasOptimizeCommands('blog:cache', 'blog:clear')",
    ],
    ['hasStubs()', 'stubs', '->hasStubs()'],
    [
        'hasProviders()',
        'providers',
        "->hasProviders(['../stubs/BlogProvider.stub'])",
    ],
    ['hasInstallCommand()', 'install-command', '->hasInstallCommand()'],
    ['hasAbout()', 'about-command', '->hasAbout()'],
];

/** The chips a first-time reader arrives to: a package that already makes sense. */
const PRESET = ['hasConfig()', 'hasRoutes()', 'hasMigrations()', 'hasViews()'];

/* --------------------------------------------------------------- vocabulary */

/* Console colours. These sit inside a console panel, which is the one surface
   that stays dark in daylight — so they are absolute rather than themed, and the
   red is the lit one from the mark rather than the darkened link red. */
const T_PROMPT = 'text-[#f87171]';
const T_CMD = 'text-white';
const T_OK = 'text-mint';
const T_DIM = 'text-code-ink/85';

/**
 * A bounded column, with the frame's own vertical rules and a joint mark where
 * they cross the rule above. The whole landing page is drawn inside it, so the
 * page reads as one ruled sheet rather than a stack of unrelated bands.
 */
function frame(inner, padding = 'py-16 sm:py-24') {
    const cross =
        '<span class="mark-cross pointer-events-none absolute hidden size-[11px] lg:block" aria-hidden="true"';

    return `<div class="relative mx-auto w-full max-w-7xl px-5 sm:px-8 lg:border-x lg:border-band-line ${padding}">
    ${cross} style="top:-6px;left:-6px"></span>
    ${cross} style="top:-6px;right:-6px"></span>
    ${inner}
  </div>`;
}

/** An inset panel: the artifact a claim is standing on. */
const ARTIFACT =
    'overflow-x-auto rounded-lg border border-band-line bg-code p-4 font-mono ' +
    'text-[0.75rem] leading-[1.9] [font-variant-ligatures:none]';

/**
 * Console output that types itself out, one line at a time.
 *
 * Each line carries its index, and the stylesheet turns that into a delay — so
 * the block reads as a command that ran rather than as a screenshot of one. In a
 * section that has not been scrolled to yet the run is paused, so a reader
 * arriving at the card sees it from the first line.
 */
function consoleBlock(lines, extra = '') {
    return `<div class="${ARTIFACT} ${extra}">
      <div class="lp-seq min-w-max text-code-ink">
        ${lines.map((line, index) => `<div style="--i:${index}">${line || '&#8203;'}</div>`).join('')}
      </div>
    </div>`;
}

const TAGS = `<div class="${ARTIFACT}">
      <div class="lp-seq grid grid-cols-2 gap-x-6">
        ${[
            'config',
            'routes',
            'migrations',
            'seeders',
            'factories',
            'views',
            'translations',
            'assets',
            'stubs',
            'providers',
        ]
            .map(
                (tag, index) =>
                    `<div style="--i:${index}"><span class="${T_PROMPT}">blog::</span><span class="text-code-ink">${tag}</span></div>`,
            )
            .join('')}
      </div>
    </div>`;

const INSTALL_OUTPUT = consoleBlock([
    `<span class="${T_PROMPT}">$</span> <span class="${T_CMD}">php artisan blog:install</span>`,
    '',
    `<span class="${T_CMD}">🚀 Installing Blog</span>`,
    '',
    `<span class="${T_DIM}">(1/3)</span> Publishing configuration...`,
    `  <span class="${T_OK}">✅ Published config</span>`,
    `<span class="${T_DIM}">(2/3)</span> Publishing migrations...`,
    `  <span class="${T_OK}">✅ Published migrations</span>`,
    `<span class="${T_DIM}">(3/3)</span> Publishing assets...`,
    `  <span class="${T_OK}">✅ Published assets</span>`,
    '',
    `<span class="${T_OK} font-medium">✨ Blog installed successfully!</span>`,
]);

const MIRROR = consoleBlock([
    `<span class="${T_DIM}">// resources/views/layout.blade.php</span>`,
    `<span class="${T_PROMPT}">@packageScripts</span>(<span class="${T_CMD}">'blog'</span>)`,
    '',
    `<span class="${T_OK}">→</span> &lt;script src="/vendor/blog/js/blog.js?id=1786230412"&gt;`,
    `<span class="${T_DIM}">   copied on first resolve, cache-busted by mtime</span>`,
]);

const CARDS = [
    {
        kicker: 'predictable tags',
        title: 'Publishing, solved',
        url: 'publishing',
        artifact: TAGS,
        body: `Every resource lands under <code>package::group</code>, in the directory Laravel
      expects to find it in. The classic flat <code>package-group</code> format is one call away,
      and both can be registered at once.`,
    },
    {
        kicker: 'no publish step',
        title: 'Assets that stay published',
        url: 'assets',
        artifact: MIRROR,
        body: `The asset mirror keeps <code>public/vendor/your-package</code> in step with what you
      ship — lazily, atomically, and cache-busted by the published copy's mtime, so an upgrade
      takes effect without anyone running a command.`,
    },
    {
        kicker: 'one call, one command',
        title: 'An installer your users trust',
        url: 'install-command',
        artifact: INSTALL_OUTPUT,
        body: `<code>hasQuickInstall()</code> registers <code>php artisan blog:install</code>, publishes
      what the package declared and reports what it did — with presets, hooks and
      environment-aware publishing when you want them.`,
    },
];

/**
 * The mental model, as two panels rather than two paragraphs. The library's whole
 * claim is that there are exactly two objects, so the section is built out of
 * exactly two panels, each showing the side of the work it owns.
 */
const OBJECTS = [
    {
        name: 'Packager',
        role: 'what the package has',
        body: `The description. Every method is a <code>hasX()</code> builder returning
      <code>$this</code>, so configuration is one chain — and it validates eagerly, so a missing
      directory fails while you are building the package, not on a user's machine six months
      later.`,
        code: `$packager
    ->name('Blog')
    ->hasViews();`,
    },
    {
        name: 'PackageServiceProvider',
        role: 'when Laravel is ready for it',
        body: `The machinery. It creates the <code>Packager</code>, hands it to your
      <code>configure()</code>, then acts on the description at the two moments Laravel gives it:
      <code>register()</code> and <code>boot()</code>.`,
        code: `$this->loadViewsFrom($views, 'blog');

$this->publishes([
    $views => resource_path('views/vendor/blog'),
], 'blog::views');`,
    },
];

/* ------------------------------------------------------------- house styles */

const KICKER =
    'flex items-center gap-2 font-mono text-xs tracking-wide text-band-muted ' +
    "before:text-accent before:content-['//']";

/* Hover changes the fill and nothing else. A button that also lifts, glows and
   grows is three animations reporting the same one fact. */
const CTA =
    'inline-flex items-center gap-2 rounded-md bg-accent-solid px-5 py-3 text-sm font-semibold ' +
    'text-white no-underline transition-colors hover:bg-accent-solid-hover hover:text-white';

const CTA_INK =
    'inline-flex items-center gap-2 rounded-md border border-band-line bg-band-soft px-5 py-3 ' +
    'text-sm font-semibold text-band-ink no-underline transition-colors hover:border-accent ' +
    'hover:bg-band-raised hover:text-band-ink';

/* One accent phrase per heading, in the accent — the same emphasis the hero
   makes, at the size a section deserves. */
const SECTION_H2 =
    'font-display text-[1.75rem] font-extrabold leading-[1.05] tracking-[-0.04em] text-band-ink ' +
    'sm:text-[2.5rem] [&_em]:not-italic [&_em]:text-accent';

/* A panel. No hover state: these two are not links and nothing happens when you
   point at them, so lighting them up would be a promise the page cannot keep. */
const PANEL = 'rounded-xl border border-band-line bg-band-raised p-5';

/* The same panel where the whole card *is* a link. Hover moves the border to the
   accent and stops there — a grid of cards that each lift and cast a coloured
   shadow reads as a page that cannot hold still. */
const CARD = `${PANEL} transition-colors duration-150 hover:border-accent`;

/**
 * A window onto code.
 *
 * The whole panel sits on the code surface — the tab strip and the caption
 * included — rather than being a light card with a dark hole cut in it. In
 * daylight that hole was the problem: a dark block sandwiched between two white
 * bands reads as something failing to load, where one dark panel reads as what
 * it is, an editor.
 */
const WINDOW = 'overflow-hidden rounded-xl border border-code-line bg-code';

/** A strip of chrome inside that window: a tab row, a filename, a caption. */
const WINDOW_CHROME = 'bg-white/[0.03] text-code-ink';

/** Body copy, with the inline code the landing page's paragraphs are full of. */
const PROSE =
    'text-band-muted [&_code]:rounded-xs [&_code]:bg-band-soft-strong [&_code]:px-1 [&_code]:font-mono ' +
    '[&_code]:text-[0.85em] [&_code]:text-band-ink';

/**
 * The chain, with every builder in it. `docs.js` hides the lines whose chip is
 * not pressed and moves the closing `;` onto whichever line ends up last, so the
 * snippet is always valid PHP rather than a chain with a semicolon in the middle.
 */
function builderChain(renderCode) {
    const body = [
        'class BlogServiceProvider extends PackageServiceProvider',
        '{',
        '    public function configure(Packager $packager): void',
        '    {',
        '        $packager',
        "            ->name('Blog')",
        ...BUILDERS.map(([, , call]) => `            ${call}`),
        '    }',
        '}',
    ].join('\n');

    return renderCode(body, 'php');
}

function lineCount(code) {
    return code.trim().split('\n').length;
}

/**
 * The hero's evidence: the same provider written twice, in a panel with the two
 * versions on file tabs, and the command they both end up serving underneath.
 */
function heroCard(renderCode) {
    const hand = lineCount(BY_HAND);
    const toolkit = lineCount(WITH_TOOLKIT);

    const tab = (id, label, count, selected) =>
        `<button class="-mb-px border-b-2 border-transparent px-3 py-2.5 font-mono text-xs text-code-ink transition-colors hover:text-code-ink aria-selected:border-accent aria-selected:text-white" type="button" role="tab" id="provider-tab-${id}"
        aria-selected="${selected ? 'true' : 'false'}" aria-controls="provider-pane-${id}"
        tabindex="${selected ? '0' : '-1'}" data-provider-tab="${id}" data-provider-lines="${count}">
        ${escape(label)}
      </button>`;

    const pane = (id, code, selected) =>
        `<div class="provider__pane" id="provider-pane-${id}" role="tabpanel"
        aria-labelledby="provider-tab-${id}" data-provider-pane="${id}"${selected ? '' : ' hidden'}>
        ${codeBlock(renderCode(code, 'php'), 'php', 'Copy this provider')}
      </div>`;

    return `<div class="group/provider ${WINDOW} shadow-2xl" data-provider>
      <div class="${WINDOW_CHROME} flex items-end justify-between gap-3 border-b border-code-line pl-2 pr-3">
        <div class="flex" role="tablist" aria-label="Two ways to write the same provider">
          ${tab('hand', 'By hand', hand, true)}
          ${tab('toolkit', 'With the toolkit', toolkit, false)}
        </div>
        <p class="shrink-0 py-2.5 font-mono text-xs"><span class="text-code-ink transition-colors group-data-[state=toolkit]/provider:text-mint" data-provider-count>${hand}</span> lines</p>
      </div>

      <!-- Tall enough for the declared provider to be read in full, caret and
           all — that is the payoff. The hand-written one is longer than the box
           and scrolls, which is the argument. -->
      <div class="max-h-[24rem] overflow-y-auto sm:max-h-[30rem] [&_.code-block_pre_code]:text-[0.8rem]
                  [&_.code-block]:my-0 [&_.code-block]:rounded-none [&_.code-block]:border-0 [&_.code-block]:shadow-none">
        ${pane('hand', BY_HAND, true)}
        ${pane('toolkit', WITH_TOOLKIT, false)}
      </div>

      <p class="${WINDOW_CHROME} flex flex-wrap items-center gap-x-2 gap-y-1 border-t border-code-line px-4 py-2.5 text-[0.75rem]">
        <span class="font-mono text-code-ink">src/BlogServiceProvider.php</span>
        Both providers do the same thing: load, publish and tag every resource the package ships.
      </p>
    </div>`;
}

export function home({
    page,
    content,
    base,
    site,
    version,
    renderCode,
    editUrl,
}) {
    const description = page.description || site.description;
    const canonical = `${site.origin}${base}`;
    const install = 'composer require nyoncode/laravel-package-toolkit';

    const cards = CARDS.map(
        (
            card,
            index,
        ) => `<a class="${CARD} group flex flex-col gap-4 no-underline${
            index === 2
                ? ' md:col-span-2 md:flex-row md:items-start [&>*]:md:flex-1'
                : ''
        }" href="${base}${card.url}/">
        ${card.artifact}
        <div class="flex flex-col items-start gap-2">
          <p class="${KICKER}">${escape(card.kicker)}</p>
          <h3 class="font-display text-lg font-bold tracking-tight text-band-ink">${escape(card.title)}</h3>
          <p class="${PROSE} text-sm leading-relaxed">${card.body}</p>
          <span class="mt-1 flex items-center gap-1.5 text-sm font-medium text-accent">Read more <span class="transition-transform duration-300 group-hover:translate-x-1">${icon.arrowRight}</span></span>
        </div>
      </a>`,
    ).join('');

    const facts = [
        ['PHP 8.2+', '8.2 · 8.3 · 8.4 · 8.5'],
        ['Laravel 12 &amp; 13', '12.61.1+ · 13.12.0+'],
        ['24 builders', 'one per resource type'],
        ['MIT', 'no runtime dependency'],
    ]
        .map(
            ([
                value,
                label,
            ]) => `<div class="flex flex-col justify-center gap-1 px-5 py-5 sm:px-6">
        <span class="font-display text-[1.05rem] font-extrabold tracking-[-0.03em] text-band-ink">${value}</span>
        <span class="font-mono text-[0.6875rem] text-band-muted">${label}</span>
      </div>`,
        )
        .join('');

    return `${documentHead({
        title: `${site.title} — build Laravel packages without the boilerplate`,
        description,
        canonical,
        base,
        site,
        pageId: 'home',
        // The landing page has its own ground, one step away from the
        // documentation's — but it follows the reader's theme like everything else.
        bodyClass: 'is-landing bg-band text-band-ink',
        markdown: `${base}index.md`,
    })}
${masthead({
    base,
    version,
    site,
    withDrawer: false,
    docsUrl: `${base}quickstart/`,
    onInk: true,
})}
<main id="content">
  <section class="relative overflow-hidden">
    <!-- Two flat layers behind the copy: a drafting grid, and one pool of the
         brand red that breathes slowly under the headline. -->
    <div class="bg-blueprint-lg pointer-events-none absolute inset-0 [mask-image:radial-gradient(ellipse_75%_65%_at_35%_0%,black,transparent)]"></div>
    <div class="lp-glow pointer-events-none absolute left-[35%] top-0 h-[26rem] w-[46rem] max-w-[120%] -translate-x-1/2 -translate-y-2/3 rounded-full bg-accent-solid/12 blur-[130px] dark:bg-accent-solid/25"></div>

    ${frame(
        /* The right column is the wider one: the longest line of the declared
           provider has to fit without the card scrolling sideways. */
        `<div class="grid items-center gap-12 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.07fr)] lg:gap-14">
        <!-- min-w-0: a grid item's automatic minimum is its min-content, and the
             install button is wider than a phone. -->
        <div class="lp-enter flex w-full min-w-0 flex-col items-start">
          <p class="${KICKER}">for laravel package authors</p>

          <!-- "has" is the word the documentation itself emphasises — it is what
               the Packager records — so it is underlined in the accent rather
               than filled with it. The second line is the accent, stated
               plainly: gradient-filled headlines are the house style of every
               framework landing page built since 2021. -->
          <h1 class="mt-5 text-balance font-display text-[clamp(2.45rem,1.5rem+5.4vw,4.3rem)] font-extrabold leading-[0.96] tracking-[-0.05em] text-band-ink">
            Describe what your package
            <em class="not-italic underline decoration-accent [text-decoration-thickness:0.06em] [text-underline-offset:0.14em]">has</em>.
            <span class="block text-[#ef4444]">The toolkit wires it.</span>
          </h1>

          <p class="${PROSE} mt-6 max-w-xl text-base leading-relaxed sm:text-lg">
            Config, routes, migrations, views, assets, commands — declared once in
            <code>configure()</code>. The provider does the <code>loadX()</code> calls, the
            <code>publishes()</code> mapping and the publish tags for you, at the moment Laravel
            expects each of them.
          </p>

          <div class="mt-8 flex w-full flex-wrap gap-3 [&>a]:justify-center max-sm:[&>a]:w-full">
            <a class="${CTA}" href="${base}quickstart/">Build your first package ${icon.arrowRight}</a>
            <a class="${CTA_INK}" href="${base}api-reference/">API reference</a>
          </div>

          <!-- Sized to its own text rather than to the column: this is a command
               to be read and copied, and a box that stretches to the grid turns
               it into a form field. -->
          <button class="group mt-6 flex w-fit max-w-full items-center gap-3.5 rounded-md border border-band-line bg-band-soft py-2 pl-3.5 pr-2.5 text-left transition-colors hover:border-accent hover:bg-band-raised [&.is-copied]:border-mint/60" type="button" data-copy-text="${escape(install)}">
            <code class="min-w-0 truncate font-mono text-[0.8125rem] text-band-ink">
              <span class="mr-1.5 select-none text-mint">$</span>${escape(install)}
            </code>
            <span class="grid size-6 shrink-0 place-items-center rounded-sm bg-band-raised text-band-muted ring-1 ring-band-line transition-colors group-hover:text-band-ink group-[.is-copied]:text-mint" aria-hidden="true">
              <span class="group-[.is-copied]:hidden">${icon.copy}</span>
              <span class="hidden group-[.is-copied]:block">${icon.check}</span>
            </span>
            <span class="sr-only">Copy the install command</span>
          </button>

          <p class="mt-4 font-mono text-xs text-band-muted">PHP 8.2+ · Laravel 12 &amp; 13 · MIT licensed</p>
        </div>

        <div class="lp-enter min-w-0 [&>*]:[animation-delay:340ms]">
          ${heroCard(renderCode)}
        </div>
      </div>`,
        'py-14 sm:py-20',
    )}
  </section>

  <section class="border-t border-band-line" data-reveal>
    ${frame(
        `<div class="lp-stagger grid grid-cols-2 border-y border-band-line sm:grid-cols-5 sm:divide-x sm:divide-band-line">
        <div class="${KICKER} col-span-2 whitespace-nowrap px-5 py-5 sm:col-span-1 sm:px-6">
          works with
        </div>
        ${facts}
      </div>`,
        'py-0',
    )}
  </section>

  <section class="border-t border-band-line" data-reveal>
    ${frame(`<div class="lp-stagger"><header class="flex max-w-2xl flex-col gap-3">
        <p class="${KICKER}">the vocabulary</p>
        <h2 class="${SECTION_H2}">Everything a package ships, <em>declared</em></h2>
        <p class="${PROSE} text-base leading-relaxed">
          One builder per resource type, each with a matching <code>bootX()</code> or
          <code>publishX()</code> on the provider. Switch them on and off — this is the provider
          you would write.
        </p>
      </header>

      <!-- Chips on the left, the chain they are writing on the right. Below two
           columns the chain comes first in the source and stays pinned: with
           twenty-five chips stacked above it, a reader toggling the last one
           would be changing something they cannot see. -->
      <div class="mt-10 grid gap-7 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.05fr)] lg:items-start" data-builder-widget>
        <div class="sticky top-[calc(var(--masthead-h)+0.75rem)] z-10 lg:col-start-2 lg:row-start-1" data-builder-out>
          <div class="overflow-hidden rounded-xl border border-band-line bg-band-raised shadow-xl
                      [&_.code-block]:my-0 [&_.code-block]:rounded-none [&_.code-block]:border-0 [&_.code-block]:shadow-none">
            <p class="border-b border-band-line px-4 py-2.5 font-mono text-xs text-band-muted">src/BlogServiceProvider.php</p>
            ${codeBlock(builderChain(renderCode), 'php', 'Copy this provider')}
            <div class="flex items-center gap-3 border-t border-band-line px-4 py-2.5 text-xs">
              <p class="font-mono text-band-muted"><span class="text-band-ink" data-builder-count>0</span> lines</p>
              <a class="truncate font-mono text-mint no-underline hover:underline" href="${base}config/" data-builder-docs hidden></a>
              <button class="ml-auto shrink-0 rounded-md border border-band-line px-2.5 py-1 font-medium text-band-muted transition-colors hover:border-band-line-strong hover:text-band-ink [&.is-copied]:border-mint/50 [&.is-copied]:text-mint" type="button" data-builder-copy>Copy provider</button>
            </div>
          </div>
        </div>

        <div class="mt-6 flex flex-wrap gap-2 lg:col-start-1 lg:row-start-1 lg:mt-0" role="group" aria-label="Resources this package declares">
          ${BUILDERS.map(
              ([
                  label,
                  url,
                  call,
              ]) => `<button class="rounded-md border border-band-line bg-band-raised px-2.5 py-2 text-left font-mono text-[0.775rem] whitespace-nowrap text-band-muted transition-[transform,color,background-color,border-color] duration-150 hover:-translate-y-px hover:border-accent hover:bg-accent/8 hover:text-accent-hover aria-pressed:border-accent aria-pressed:bg-accent/8 aria-pressed:text-accent-hover
              before:mr-[0.35em] before:font-semibold before:opacity-0 before:content-['✓'] aria-pressed:before:opacity-100"
              type="button"
              aria-pressed="${PRESET.includes(label) ? 'true' : 'false'}"
              data-builder="${escape(call)}" data-builder-url="${base}${url}/"
              data-builder-label="${escape(label)}">${escape(label)}</button>`,
          ).join('')}
        </div>
      </div></div>`)}
  </section>

  <section class="border-t border-band-line" data-reveal>
    ${frame(`<div class="lp-stagger"><header class="flex max-w-2xl flex-col gap-3">
        <p class="${KICKER}">what you get</p>
        <h2 class="${SECTION_H2}">Three things you no longer <em>maintain</em></h2>
      </header>
      <div class="lp-stagger mt-10 grid gap-5 md:grid-cols-2">${cards}</div></div>`)}
  </section>

  <section class="border-t border-band-line" data-reveal>
    ${frame(`<div class="lp-stagger"><header class="flex max-w-2xl flex-col gap-3">
        <p class="${KICKER}">the mental model</p>
        <h2 class="${SECTION_H2}">Two objects, and <em>nothing hidden</em></h2>
        <div class="${PROSE} text-base leading-relaxed [&_p]:mt-0">${content}</div>
      </header>

      <div class="lp-stagger mt-10 grid gap-5 md:grid-cols-2">
        ${OBJECTS.map(
            object => `<div class="${PANEL} flex flex-col gap-4">
            <div class="flex flex-col items-start gap-2">
              <p class="${KICKER}">${escape(object.role)}</p>
              <h3 class="font-display text-lg font-bold tracking-tight text-band-ink"><code class="font-mono">${escape(object.name)}</code></h3>
              <p class="${PROSE} text-sm leading-relaxed">${object.body}</p>
            </div>
            <div class="mt-auto overflow-hidden rounded-lg border border-band-line bg-code
                        [&_.code-block]:my-0 [&_.code-block]:rounded-none [&_.code-block]:border-0 [&_.code-block]:shadow-none">
              ${codeBlock(renderCode(object.code, 'php'), 'php', 'Copy this snippet')}
            </div>
          </div>`,
        ).join('')}
      </div></div>`)}
  </section>

  <section class="relative overflow-hidden border-t border-band-line" data-reveal>
    <div class="lp-glow pointer-events-none absolute bottom-0 left-1/2 h-[20rem] w-[40rem] max-w-[120%] -translate-x-1/2 translate-y-1/2 rounded-full bg-accent-solid/10 blur-[120px] dark:bg-accent-solid/20"></div>
    ${frame(`<div class="lp-stagger flex flex-col items-center gap-5 text-center">
      <h2 class="${SECTION_H2} max-w-2xl">Start with a <em>working package</em></h2>
      <p class="${PROSE} max-w-xl text-base leading-relaxed">
        The quickstart goes from an empty directory to a package with config, routes, views,
        migrations and its own <code>artisan install</code> command — in one page.
      </p>
      <div class="mt-2 flex w-full flex-wrap justify-center gap-3 max-sm:[&>a]:w-full">
        <a class="${CTA}" href="${base}quickstart/">Build your first package ${icon.arrowRight}</a>
        <a class="${CTA_INK}" href="${site.repository}" rel="noopener noreferrer" target="_blank">
          ${icon.github} View on GitHub
        </a>
      </div>
    </div>`)}
  </section>
</main>

<footer class="border-t border-band-line">
  ${frame(
      `<div class="grid gap-10 sm:grid-cols-[minmax(0,1fr)_minmax(0,2fr)]">
      <div class="flex flex-col items-start gap-2">
        ${icon.logo('footer', 32)}
        <p class="font-display text-sm font-bold tracking-tight text-band-ink">Laravel Package Toolkit</p>
        <p class="max-w-xs text-sm text-band-muted">Build Laravel packages without the boilerplate.</p>
      </div>

      <nav class="grid grid-cols-2 gap-6 sm:grid-cols-3" aria-label="Footer">
        <div>
          <p class="mb-2 font-mono text-[0.6875rem] uppercase tracking-[0.12em] text-band-muted">Getting started</p>
          <ul class="space-y-1.5 text-sm [&_a]:text-band-muted [&_a]:no-underline hover:[&_a]:text-accent">
            <li><a href="${base}installation/">Installation</a></li>
            <li><a href="${base}quickstart/">Quickstart</a></li>
            <li><a href="${base}service-provider/">The service provider</a></li>
            <li><a href="${base}packager/">The Packager</a></li>
          </ul>
        </div>
        <div>
          <p class="mb-2 font-mono text-[0.6875rem] uppercase tracking-[0.12em] text-band-muted">Reference</p>
          <ul class="space-y-1.5 text-sm [&_a]:text-band-muted [&_a]:no-underline hover:[&_a]:text-accent">
            <li><a href="${base}api-reference/">API reference</a></li>
            <li><a href="${base}publishing/">Publishing</a></li>
            <li><a href="${base}testing/">Testing</a></li>
          </ul>
        </div>
        <div>
          <p class="mb-2 font-mono text-[0.6875rem] uppercase tracking-[0.12em] text-band-muted">Project</p>
          <ul class="space-y-1.5 text-sm [&_a]:text-band-muted [&_a]:no-underline hover:[&_a]:text-accent">
            <li><a href="${site.repository}" rel="noopener noreferrer" target="_blank">GitHub</a></li>
            <li><a href="https://packagist.org/packages/nyoncode/laravel-package-toolkit" rel="noopener noreferrer" target="_blank">Packagist</a></li>
            <li><a href="${base}upgrade/">Upgrade guide</a></li>
            <li><a href="${editUrl}" rel="noopener noreferrer" target="_blank">Edit this page</a></li>
          </ul>
        </div>
      </nav>
    </div>

    <p class="mt-10 border-t border-band-line pt-6 text-xs text-band-muted">MIT licensed · © NyonCode · Not affiliated with Laravel LLC</p>`,
      'py-12',
  )}
</footer>

${searchDialog()}
${scripts(base)}`;
}
