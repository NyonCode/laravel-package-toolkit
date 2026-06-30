# Changelog

All notable changes to `laravel-package-toolkit` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/).

## [2.0.2] - 2026-06-30

### Fixed

- **About command data not merging** — Custom about data returned from `aboutData()`
  on a service provider was never included in the `php artisan about` output; only the
  package `Version` was displayed. The provider's `aboutData()` is now passed through to
  the packager and merged with the version data as documented.
