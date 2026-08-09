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
 * whichever path the build is using (Torchlight or the offline fallback).
 */

import { documentHead, escape, icon, masthead, scripts, searchDialog } from './chrome.mjs'

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
}`

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
}`

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
  ['hasBroadcastChannels()', 'broadcast-channels', "->hasBroadcastChannels(['channels.php'])"],
  ['hasMigrations()', 'migrations', '->hasMigrations()'],
  ['hasSeeders()', 'seeders', '->hasSeeders()'],
  ['hasFactories()', 'factories', '->hasFactories()'],
  ['hasTranslations()', 'translations', '->hasTranslations()'],
  ['hasViews()', 'views', '->hasViews()'],
  ['hasComponents()', 'view-components', "->hasComponents(['alert' => Alert::class])"],
  ['hasComponentNamespace()', 'view-components', "->hasComponentNamespace('Vendor\\\\Blog\\\\View\\\\Components')"],
  ['hasViewComposer()', 'view-composers', "->hasViewComposer('blog::sidebar', SidebarComposer::class)"],
  ['hasSharedDataForAllViews()', 'view-composers', "->hasSharedDataForAllViews(['brand' => 'Blog'])"],
  ['hasAssets()', 'assets', '->hasAssets()'],
  ['hasViteAssets()', 'assets', "->hasViteAssets(['resources/js/blog.js'])"],
  ['hasMiddlewareAliases()', 'middleware', "->hasMiddlewareAliases(['author' => EnsureAuthor::class])"],
  ['hasMiddlewareGroups()', 'middleware', "->hasMiddlewareGroups(['web' => [TrackReads::class]])"],
  ['hasMiddlewareGlobals()', 'middleware', '->hasMiddlewareGlobals([TrackReads::class])'],
  ['hasEvents()', 'events', '->hasEvents([Published::class => Notify::class])'],
  ['hasSubscribers()', 'events', '->hasSubscribers([BlogSubscriber::class])'],
  ['hasCommands()', 'commands', '->hasCommands()'],
  ['hasOptimizeCommands()', 'optimize', "->hasOptimizeCommands('blog:cache', 'blog:clear')"],
  ['hasStubs()', 'stubs', '->hasStubs()'],
  ['hasProviders()', 'providers', "->hasProviders(['../stubs/BlogProvider.stub'])"],
  ['hasInstallCommand()', 'install-command', '->hasInstallCommand()'],
  ['hasAbout()', 'about-command', '->hasAbout()'],
]

/** The chips a first-time reader arrives to: a package that already makes sense. */
const PRESET = ['hasConfig()', 'hasRoutes()', 'hasMigrations()', 'hasViews()']

const TAGS = `<div class="tag-grid">
        <span><b>blog::</b>config</span><span><b>blog::</b>routes</span>
        <span><b>blog::</b>migrations</span><span><b>blog::</b>seeders</span>
        <span><b>blog::</b>factories</span><span><b>blog::</b>views</span>
        <span><b>blog::</b>translations</span><span><b>blog::</b>assets</span>
        <span><b>blog::</b>stubs</span><span><b>blog::</b>providers</span>
      </div>`

const INSTALL_OUTPUT = `<pre><code><span class="t-prompt">$</span> <span class="t-cmd">php artisan blog:install</span>

🚀 Installing Blog

<span class="t-step">(1/3)</span> Publishing configuration...
  <span class="t-ok">✅ Published config</span>
<span class="t-step">(2/3)</span> Publishing migrations...
  <span class="t-ok">✅ Published migrations</span>
<span class="t-step">(3/3)</span> Publishing assets...
  <span class="t-ok">✅ Published assets</span>

<span class="t-done">✨ Blog installed successfully!</span></code></pre>`

const MIRROR = `<pre><code><span class="t-step">// resources/views/layout.blade.php</span>
&lt;script src="{{ app(PublishedAssets::class)
    -&gt;url('blog', $js) }}"&gt;&lt;/script&gt;

<span class="t-ok">→</span> /vendor/blog/app.js?id=1786230412
<span class="t-step">   copied on first resolve, cache-busted by mtime</span></code></pre>`

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
]

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
]

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
  ].join('\n')

  return renderCode(body, 'php')
}

function lineCount(code) {
  return code.trim().split('\n').length
}

function heroCard(renderCode) {
  const hand = lineCount(BY_HAND)
  const toolkit = lineCount(WITH_TOOLKIT)

  const tab = (id, label, count, selected) =>
    `<button class="provider__tab" type="button" role="tab" id="provider-tab-${id}"
        aria-selected="${selected ? 'true' : 'false'}" aria-controls="provider-pane-${id}"
        tabindex="${selected ? '0' : '-1'}" data-provider-tab="${id}" data-provider-lines="${count}">
        ${escape(label)}
      </button>`

  // Wrapped in `.code-block` so the hero borrows the documentation's own code
  // styling — including the copy button, which reads the untouched source out of
  // the hidden textarea the renderer leaves behind.
  const pane = (id, code, selected) =>
    `<div class="provider__pane" id="provider-pane-${id}" role="tabpanel"
        aria-labelledby="provider-tab-${id}" data-provider-pane="${id}"${selected ? '' : ' hidden'}>
        <div class="code-block" data-language="php">
          <button class="code-copy" type="button" data-copy aria-label="Copy this provider"><span data-copy-label>Copy</span></button>
          ${renderCode(code, 'php')}
        </div>
      </div>`

  return `<div class="provider" data-provider>
      <div class="provider__head">
        <div class="provider__tabs" role="tablist" aria-label="Two ways to write the same provider">
          ${tab('hand', 'By hand', hand, true)}
          ${tab('toolkit', 'With the toolkit', toolkit, false)}
        </div>
        <p class="provider__count"><span data-provider-count>${hand}</span> lines</p>
      </div>
      <div class="provider__body">
        ${pane('hand', BY_HAND, true)}
        ${pane('toolkit', WITH_TOOLKIT, false)}
      </div>
      <p class="provider__note">
        <span class="provider__file">src/BlogServiceProvider.php</span>
        Both providers do the same thing: load, publish and tag every resource the package ships.
      </p>
    </div>`
}

export function home({ page, content, base, site, version, renderCode, editUrl }) {
  const description = page.description || site.description
  const canonical = `${site.origin}${base}`
  const install = 'composer require nyoncode/laravel-package-toolkit'

  const cards = CARDS.map(
    (card) => `<a class="lp-card" href="${base}${card.url}/">
        <div class="lp-card__artifact">${card.artifact}</div>
        <div class="lp-card__body">
          <p class="lp-kicker">${escape(card.kicker)}</p>
          <h3>${escape(card.title)}</h3>
          <p>${card.body}</p>
          <span class="lp-card__more">Read more ${icon.arrowRight}</span>
        </div>
      </a>`,
  ).join('')

  return `${documentHead({
    title: `${site.title} — build Laravel packages without the boilerplate`,
    description,
    canonical,
    base,
    site,
    pageId: 'home',
    bodyClass: 'is-landing',
    markdown: `${base}index.md`,
  })}
${masthead({ base, version, site, variant: 'over-ink', withDrawer: false, docsUrl: `${base}quickstart/` })}
<main id="content">
  <section class="lp-hero">
    <div class="lp-frame">
    <div class="lp-hero__grid">
      <div class="lp-hero__copy">
        <p class="lp-eyebrow">for laravel package authors</p>

        <h1 class="lp-title">
          Describe what your package <em>has</em>.
          <span class="lp-title__accent">The toolkit wires it.</span>
        </h1>

        <p class="lp-lead">
          Config, routes, migrations, views, assets, commands — declared once in
          <code>configure()</code>. The provider does the <code>loadX()</code> calls, the
          <code>publishes()</code> mapping and the publish tags for you, at the moment Laravel
          expects each of them.
        </p>

        <div class="lp-actions">
          <a class="cta" href="${base}quickstart/">Build your first package ${icon.arrowRight}</a>
          <a class="cta cta--ghost" href="${base}api-reference/">API reference</a>
        </div>

        <button class="lp-install" type="button" data-copy-text="${escape(install)}">
          <code><span class="lp-install__prompt">$</span> ${escape(install)}</code>
          <span class="lp-install__action" aria-hidden="true">${icon.copy}${icon.check}</span>
          <span class="sr-only">Copy the install command</span>
        </button>

        <p class="lp-meta">PHP 8.2+ · Laravel 12 &amp; 13 · MIT licensed</p>
      </div>

      <div class="lp-hero__demo">
        ${heroCard(renderCode)}
      </div>
    </div>
    </div>
  </section>

  <section class="lp-strip" data-reveal>
    <div class="lp-frame">
      <div class="lp-strip__inner">
        <p class="lp-strip__label">works with</p>
        <div class="lp-fact"><span class="lp-fact__value">PHP 8.2+</span><span class="lp-fact__label">8.2 · 8.3 · 8.4 · 8.5</span></div>
        <div class="lp-fact"><span class="lp-fact__value">Laravel 12 &amp; 13</span><span class="lp-fact__label">12.61.1+ · 13.12.0+</span></div>
        <div class="lp-fact"><span class="lp-fact__value">24 builders</span><span class="lp-fact__label">one per resource type</span></div>
        <div class="lp-fact"><span class="lp-fact__value">MIT</span><span class="lp-fact__label">no runtime dependency</span></div>
      </div>
    </div>
  </section>
  <section class="lp-section lp-section--resources" data-reveal>
    <div class="lp-frame">
    <div class="lp-section__inner">
      <header class="lp-section__head">
        <p class="lp-kicker">the vocabulary</p>
        <h2>Everything a package ships, <em>declared</em></h2>
        <p class="lp-section__lead">
          One builder per resource type, each with a matching <code>bootX()</code> or
          <code>publishX()</code> on the provider. Switch them on and off — this is the provider
          you would write.
        </p>
      </header>

      <div class="lp-builder" data-builder-widget>
        <div class="lp-builder__out">
          <div class="code-block" data-language="php">
            ${builderChain(renderCode)}
          </div>
          <div class="lp-builder__foot">
            <p class="lp-builder__count"><span data-builder-count>0</span> lines</p>
            <a class="lp-builder__docs" href="${base}config/" data-builder-docs hidden></a>
            <button class="lp-builder__copy" type="button" data-builder-copy>Copy provider</button>
          </div>
        </div>
        <div class="chip-grid" role="group" aria-label="Resources this package declares">
          ${BUILDERS.map(
            ([label, url, call]) => `<button class="chip" type="button"
              aria-pressed="${PRESET.includes(label) ? 'true' : 'false'}"
              data-builder="${escape(call)}" data-builder-url="${base}${url}/"
              data-builder-label="${escape(label)}"><code>${escape(label)}</code></button>`,
          ).join('')}
        </div>

      </div>
    </div>
    </div>
  </section>

  <section class="lp-section" data-reveal>
    <div class="lp-frame">
    <div class="lp-section__inner">
      <header class="lp-section__head">
        <p class="lp-kicker">what you get</p>
        <h2>Three things you no longer <em>maintain</em></h2>
      </header>
      <div class="lp-cards">${cards}</div>
    </div>
    </div>
  </section>

  <section class="lp-section lp-section--model" data-reveal>
    <div class="lp-frame">
    <div class="lp-section__inner">
      <header class="lp-section__head">
        <p class="lp-kicker">the mental model</p>
        <h2>Two objects, and <em>nothing hidden</em></h2>
        <div class="lp-section__lead">${content}</div>
      </header>

      <div class="lp-objects">
        ${OBJECTS.map(
          (object) => `<div class="lp-object">
            <p class="lp-object__role">${escape(object.role)}</p>
            <h3><code>${escape(object.name)}</code></h3>
            <p>${object.body}</p>
            <div class="lp-object__code">
              <div class="code-block" data-language="php">
                <button class="code-copy" type="button" data-copy aria-label="Copy this snippet"><span data-copy-label>Copy</span></button>
                ${renderCode(object.code, 'php')}
              </div>
            </div>
          </div>`,
        ).join('')}
      </div>
    </div>
    </div>
  </section>

  <section class="lp-final" data-reveal>
    <div class="lp-frame">
    <div class="lp-final__inner">
      <h2>Start with a <em>working package</em></h2>
      <p>
        The quickstart goes from an empty directory to a package with config, routes, views,
        migrations and its own <code>artisan install</code> command — in one page.
      </p>
      <div class="lp-actions lp-actions--centred">
        <a class="cta" href="${base}quickstart/">Build your first package ${icon.arrowRight}</a>
        <a class="cta cta--ghost" href="${site.repository}" rel="noopener noreferrer" target="_blank">
          ${icon.github} View on GitHub
        </a>
      </div>
    </div>
    </div>
  </section>
</main>

<footer class="lp-footer">
  <div class="lp-frame">
  <div class="lp-footer__inner">
    <div class="lp-footer__brand">
      ${icon.logo('footer', 32)}
      <p class="lp-footer__name">Laravel Package Toolkit</p>
      <p class="lp-footer__tag">Build Laravel packages without the boilerplate.</p>
    </div>

    <nav class="lp-footer__nav" aria-label="Footer">
      <div>
        <p class="lp-footer__title">Getting started</p>
        <ul>
          <li><a href="${base}installation/">Installation</a></li>
          <li><a href="${base}quickstart/">Quickstart</a></li>
          <li><a href="${base}service-provider/">The service provider</a></li>
          <li><a href="${base}packager/">The Packager</a></li>
        </ul>
      </div>
      <div>
        <p class="lp-footer__title">Reference</p>
        <ul>
          <li><a href="${base}api-reference/">API reference</a></li>
          <li><a href="${base}publishing/">Publishing</a></li>
          <li><a href="${base}testing/">Testing</a></li>
        </ul>
      </div>
      <div>
        <p class="lp-footer__title">Project</p>
        <ul>
          <li><a href="${site.repository}" rel="noopener noreferrer" target="_blank">GitHub</a></li>
          <li><a href="https://packagist.org/packages/nyoncode/laravel-package-toolkit" rel="noopener noreferrer" target="_blank">Packagist</a></li>
          <li><a href="${base}upgrade/">Upgrade guide</a></li>
          <li><a href="${editUrl}" rel="noopener noreferrer" target="_blank">Edit this page</a></li>
        </ul>
      </div>
    </nav>
  </div>
  <p class="lp-footer__legal">MIT licensed · © NyonCode · Not affiliated with Laravel LLC</p>
  </div>
</footer>

${searchDialog()}
${scripts(base)}`
}
