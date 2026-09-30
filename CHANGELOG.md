# Changelog

All notable changes to `syriable/livewire-navigation` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2026-09-30

First stable release.

### Added

- `Navigation::currentUrl()`, `currentRoute()`, `previousUrl()` and
  `previousRoute()`, each with an optional fallback, available through the
  facade or by injecting `Syriable\Packages\LivewireNavigation\Navigation`.
- Correct answers during page requests, Livewire updates and other requests
  sent from a page, such as form submissions.
- Per browser tab resolution: the current and previous page are embedded in
  the signed memo of every Livewire component, and page requests use the
  same-origin `Referer` header.
- The current URL during Livewire updates includes query string changes made
  after the page loaded, such as those made by `#[Url]` properties.
- `TrackNavigation` middleware, appended to the `web` middleware group
  automatically, that records only successfully displayed HTML pages:
  redirects, error pages, JSON, downloads, AJAX and prefetch requests are
  ignored, and reloads keep the previous page.
- Only same-origin URLs are ever reported as the previous page, so it is safe
  to redirect to.
- Support for PHP 8.4+, Laravel 12 and 13, and Livewire 3.6+ and 4.

[Unreleased]: https://github.com/syriable/livewire-navigation/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/syriable/livewire-navigation/releases/tag/v1.0.0
