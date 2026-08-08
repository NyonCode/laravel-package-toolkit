---
title: Overview
layout: home
description: Laravel Package Toolkit turns the boilerplate of a Laravel package service provider into one fluent description of what your package ships.
---

## The mental model

There are exactly two objects to keep in your head.

**`Packager`** is the description. Everything on it is a `hasX()` builder that returns `$this`, so
configuration is one chain. It records *what* the package has and validates it eagerly — a missing
directory or an unreadable file fails while you are building the package, not on a user's machine
six months later.

**`PackageServiceProvider`** is the machinery. It creates a `Packager`, hands it to your
`configure()` method, and then acts on the description at the two moments Laravel gives it:
`register()` and `boot()`.

```php
// You write this…
public function configure(Packager $packager): void
{
    $packager->name('Blog')->hasViews();
}

// …and the provider does this, at boot:
// $this->loadViewsFrom($packager->views(), $packager->shortName());
// $this->publishes([$packager->views() => resource_path('views/vendor/blog')], 'blog::views');
```

Nothing is hidden behind magic. Every `hasX()` has a matching `bootX()` or `publishX()` on the
provider, and you can call, override or skip any of them.

## Requirements

| Requirement | Version |
|---|---|
| PHP | `^8.2` |
| Laravel | `12.x` (>= 12.61.1) or `13.x` (>= 13.12.0) |
| Composer | `^2.8` (the toolkit reads the installed-versions map and the PSR-4 prefixes) |

:::warning Laravel 10 and 11
Support was removed in v2.1. Both branches are past security-support end of life, and the June 2026
advisories — including the High-severity CRLF injection CVE-2026-48019 — were patched only in
Laravel 12.60+ and 13.9+, never backported. If you still need Laravel 10 or 11, pin `~2.0.0`; for
Laravel 9, pin `^1.0`.
:::

## Where to go next

- [Installation](/installation) — add the toolkit to your package's `composer.json`.
- [Quickstart](/quickstart) — a complete package, from empty directory to `artisan install`, in one page.
- [The service provider](/service-provider) — what happens during `register()` and `boot()`, in order.
- [The Packager](/packager) — names, paths, and how files are resolved.
- [API reference](/api-reference) — every public method on one page.
