# Changelog

All notable changes to `laravel-package-toolkit` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/).

## [2.1.0] - 2026-06-30

### Removed

- **Dropped Laravel 10.x support** — Laravel 10 reached end-of-life and no longer receives security updates (all
  released `10.*` versions carry unpatched security advisories). The package now requires Laravel 11, 12, or 13. If you
  still depend on Laravel 10, stay on the `~2.0.0` release.

## [2.0.2] - 2026-06-30

### Fixed

- **About command data not merging** — Custom about data returned from `aboutData()`
  on a service provider was never included in the `php artisan about` output; only the
  package `Version` was displayed. The provider's `aboutData()` is now passed through to
  the packager and merged with the version data as documented.
