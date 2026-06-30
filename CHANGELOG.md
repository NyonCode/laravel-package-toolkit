# Changelog

All notable changes to `laravel-package-toolkit` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/).

## [2.1.0] - 2026-06-30

### Removed

- **Dropped Laravel 10.x and 11.x support** — both branches have passed their security-support end-of-life. The
  June 2026 advisories (including a High-severity CRLF injection, CVE-2026-48019) were patched only in Laravel
  12.60+/13.9+ and never backported to 10.x or 11.x, so no secure release exists on those branches.

### Changed

- **Minimum Laravel version raised to 12.61.1 / 13.12.0** — the `illuminate/support` constraint is now
  `^12.61.1|^13.12.0`, pinning to the first releases patched against the June 2026 advisories. If you still depend on
  Laravel 10 or 11, stay on the `~2.0.0` release.

## [2.0.2] - 2026-06-30

### Fixed

- **About command data not merging** — Custom about data returned from `aboutData()`
  on a service provider was never included in the `php artisan about` output; only the
  package `Version` was displayed. The provider's `aboutData()` is now passed through to
  the packager and merged with the version data as documented.
