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
 * Every `hasX()` a package can declare, in the order the sidebar introduces
 * them. This is the honest surface area of the library — and, because each chip
 * is a link, the fastest route into the page that documents it.
 */
const RESOURCES = [
  ['hasConfig()', 'config'],
  ['hasRoutes()', 'routes'],
  ['hasBroadcastChannels()', 'broadcast-channels'],
  ['hasMigrations()', 'migrations'],
  ['hasSeeders()', 'seeders'],
  ['hasFactories()', 'factories'],
  ['hasTranslations()', 'translations'],
  ['hasViews()', 'views'],
  ['hasComponents()', 'view-components'],
  ['hasComponentNamespace()', 'view-components'],
  ['hasViewComposer()', 'view-composers'],
  ['hasSharedDataForAllViews()', 'view-composers'],
  ['hasAssets()', 'assets'],
  ['hasMiddlewareAliases()', 'middleware'],
  ['hasMiddlewareGroups()', 'middleware'],
  ['hasMiddlewareGlobals()', 'middleware'],
  ['hasEvents()', 'events'],
  ['hasSubscribers()', 'events'],
  ['hasCommands()', 'commands'],
  ['hasOptimizeCommands()', 'optimize'],
  ['hasStubs()', 'stubs'],
  ['hasProviders()', 'providers'],
  ['hasInstallCommand()', 'install-command'],
  ['hasAbout()', 'about-command'],
]

const CARDS = [
  {
    title: 'Publishing, solved',
    url: 'publishing',
    body: `Every resource lands under a predictable <code>package::group</code> tag, in the
      directory Laravel expects to find it in. The classic flat <code>package-group</code> format
      is one call away, and both can be registered at once.`,
  },
  {
    title: 'An install command for free',
    url: 'install-command',
    body: `One call gives your users <code>php artisan your-package:install</code> — with
      presets, before and after hooks, environment-aware publishing and progress output.`,
  },
  {
    title: 'Assets that stay published',
    url: 'assets',
    body: `The asset mirror keeps <code>public/vendor/your-package</code> in step with what you
      ship, lazily and atomically, so an upgrade takes effect without anyone running a command.`,
  },
]


/**
 * A real transcript, not a mock-up: this is what `InstallCommand` prints for the
 * provider in the hero card — three publish steps because `hasQuickInstall()`
 * selects config, migrations and assets, then the completion notes.
 */
const TERMINAL = `<div class="terminal">
      <div class="terminal__bar">
        <span class="terminal__dot"></span>
        <span class="terminal__dot"></span>
        <span class="terminal__dot"></span>
        <span class="terminal__label">your user's terminal</span>
      </div>
      <pre><code><span class="t-prompt">$</span> <span class="t-cmd">php artisan blog:install</span>

🚀 Installing Blog

<span class="t-step">(1/3)</span> Publishing configuration...
  <span class="t-ok">✅ Published config</span>
<span class="t-step">(2/3)</span> Publishing migrations...
  <span class="t-ok">✅ Published migrations</span>
<span class="t-step">(3/3)</span> Publishing assets...
  <span class="t-ok">✅ Published assets</span>

<span class="t-done">✨ Blog installed successfully!</span>

📋 Next steps:
  • Review configuration in config/blog.php
  • Run: php artisan migrate</code></pre>
    </div>`

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

  const chips = RESOURCES.map(
    ([label, url]) =>
      `<a class="chip" href="${base}${url}/"><code>${escape(label)}</code></a>`,
  ).join('')

  const cards = CARDS.map(
    (card) => `<a class="lp-card" href="${base}${card.url}/">
        <h3>${escape(card.title)}</h3>
        <p>${card.body}</p>
        <span class="lp-card__more">Read more ${icon.arrowRight}</span>
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
          <a class="cta cta--ink" href="${base}api-reference/">API reference</a>
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

  <section class="lp-section lp-section--install">
    <div class="lp-frame">
    <div class="lp-section__inner">
      <header class="lp-section__head">
        <p class="lp-kicker">what your users run</p>
        <h2>The installer comes with the declaration</h2>
        <p class="lp-section__lead">
          <code>hasQuickInstall()</code> — the last line of that provider — registers
          <code>php artisan blog:install</code>, publishes what the package declared, and reports
          what it did.
        </p>
      </header>
      ${TERMINAL}
    </div>
    </div>
  </section>

  <section class="lp-section lp-section--resources">
    <div class="lp-frame">
    <div class="lp-section__inner">
      <header class="lp-section__head">
        <p class="lp-kicker">the vocabulary</p>
        <h2>Everything a package ships, declared</h2>
        <p class="lp-section__lead">
          One builder per resource type, each with a matching <code>bootX()</code> or
          <code>publishX()</code> on the provider. Nothing is hidden — you can call, override or
          skip any of them.
        </p>
      </header>
      <div class="chip-grid">${chips}</div>
    </div>
    </div>
  </section>

  <section class="lp-section">
    <div class="lp-frame">
    <div class="lp-section__inner">
      <header class="lp-section__head">
        <p class="lp-kicker">included</p>
        <h2>The parts you would rather not maintain</h2>
      </header>
      <div class="lp-cards">${cards}</div>
    </div>
    </div>
  </section>

  <section class="lp-section lp-section--prose">
    <div class="lp-frame">
    <div class="lp-section__inner">
      <article class="prose">${content}</article>
    </div>
    </div>
  </section>

  <section class="lp-final">
    <div class="lp-frame">
    <div class="lp-final__inner">
      <h2>Start with a working package</h2>
      <p>
        The quickstart goes from an empty directory to a package with config, routes, views,
        migrations and its own <code>artisan install</code> command — in one page.
      </p>
      <div class="lp-actions lp-actions--centred">
        <a class="cta" href="${base}quickstart/">Build your first package ${icon.arrowRight}</a>
        <a class="cta cta--ink" href="${site.repository}" rel="noopener noreferrer" target="_blank">
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
          <li><a href="${base}roadmap/">Roadmap</a></li>
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
