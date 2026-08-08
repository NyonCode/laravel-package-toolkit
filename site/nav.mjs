/**
 * The documentation table of contents.
 *
 * Order here is the order in the sidebar *and* the order of the previous/next
 * pager at the foot of every page, so a page that is not listed here is not
 * built. `file` is relative to `docs/`, `url` is the clean URL segment ('' is
 * the site root).
 *
 * Groups are kept to five entries or fewer on purpose. One "Resources" group
 * holding seventeen pages is a list a reader has to read; five groups of four
 * are a map they can scan — and the sidebar collapses to the group you are in,
 * which only works if the groups mean something.
 */
export const sections = [
  {
    title: 'Prologue',
    pages: [
      { file: 'index.md', url: '', title: 'Overview' },
      { file: 'installation.md', url: 'installation' },
      { file: 'upgrade.md', url: 'upgrade' },
    ],
  },
  {
    title: 'Getting started',
    pages: [
      { file: 'quickstart.md', url: 'quickstart' },
      { file: 'service-provider.md', url: 'service-provider' },
      { file: 'packager.md', url: 'packager' },
      { file: 'lifecycle-hooks.md', url: 'lifecycle-hooks' },
      { file: 'conditional-configuration.md', url: 'conditional-configuration' },
    ],
  },
  {
    title: 'Config & routing',
    pages: [
      { file: 'config.md', url: 'config' },
      { file: 'routes.md', url: 'routes' },
      { file: 'broadcast-channels.md', url: 'broadcast-channels' },
      { file: 'middleware.md', url: 'middleware' },
    ],
  },
  {
    title: 'Database',
    pages: [
      { file: 'migrations.md', url: 'migrations' },
      { file: 'seeders.md', url: 'seeders' },
      { file: 'factories.md', url: 'factories' },
    ],
  },
  {
    title: 'Views & assets',
    pages: [
      { file: 'views.md', url: 'views' },
      { file: 'view-components.md', url: 'view-components' },
      { file: 'view-composers.md', url: 'view-composers' },
      { file: 'translations.md', url: 'translations' },
      { file: 'assets.md', url: 'assets' },
    ],
  },
  {
    title: 'Commands & wiring',
    pages: [
      { file: 'commands.md', url: 'commands' },
      { file: 'events.md', url: 'events' },
      { file: 'optimize.md', url: 'optimize' },
      { file: 'stubs.md', url: 'stubs' },
      { file: 'providers.md', url: 'providers' },
    ],
  },
  {
    title: 'Distribution',
    pages: [
      { file: 'publishing.md', url: 'publishing' },
      { file: 'install-command.md', url: 'install-command' },
      { file: 'about-command.md', url: 'about-command' },
    ],
  },
  {
    title: 'Reference',
    pages: [
      { file: 'api-reference.md', url: 'api-reference' },
      { file: 'testing.md', url: 'testing' },
      { file: 'ROADMAP.md', url: 'roadmap', title: 'Roadmap' },
    ],
  },
]

/** Flat, ordered list of every page — what the builder actually iterates. */
export const pages = sections.flatMap((section) =>
  section.pages.map((page) => ({ ...page, section: section.title })),
)
