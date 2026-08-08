/**
 * Torchlight configuration for the documentation site.
 *
 * CommonJS on purpose: the CLI loads this file with `require()`, and `site/`
 * declares `"type": "module"`, so a `.js` extension here would be parsed as ESM
 * and blow up. The build passes `--config torchlight.config.cjs` explicitly.
 *
 * Only used when `TORCHLIGHT_TOKEN` is set; without it the build falls back to
 * `lib/highlight.mjs`, which emits the same classes this config's CSS expects.
 */
module.exports = {
  token: process.env.TORCHLIGHT_TOKEN,

  // Written into `site/.torchlight-cache`, which is git-ignored. Highlighted
  // blocks are keyed by content + options, so an unchanged page costs no API
  // call on the next build.
  cache: '.torchlight-cache',

  // Deep indigo with violet keywords — the palette the site's own accent and
  // the offline fallback highlighter are both tuned against.
  theme: 'material-theme-palenight',

  host: 'https://api.torchlight.dev',

  options: {
    // Off by default: these are prose examples, not a code review, and line
    // numbers add a column of noise on a phone.
    lineNumbers: false,

    // The `+` / `-` gutter is drawn by the stylesheet from the `line-add` and
    // `line-remove` classes instead, so a block looks identical whether it came
    // back from the API or from the offline fallback. Letting Torchlight also
    // inject indicators would double them up on the Torchlight path only.
    diffIndicators: false,
  },

  highlight: {
    // Overridden by the `--input` / `--output` flags the build passes.
    input: 'dist',
    output: 'dist',
    includeGlobs: ['**/*.html'],
    excludePatterns: ['/node_modules/', '/vendor/'],
  },
}
