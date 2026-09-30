# 04 — Ecosystem comparison

Searched Packagist (2026-09-30) for `livewire url`, `livewire previous`,
`previous url`, `livewire navigation`. Results that only share keywords
(menu builders, data tables, breadcrumbs, localization, Docker SDKs) were
checked and discarded because they do not address the problem.

| Solution | Purpose | Strategy | Livewire aware | Tabs | Status |
| --- | --- | --- | --- | --- | --- |
| `ralphjsmit/livewire-urls` 1.7.0 (~466k installs) | Current/previous URL + route in Livewire | Manual middleware writes session before each GET | Header check only | No — session-global | Maintained; last changes are version-constraint bumps (see 05/`timeline`) |
| Laravel `url()->previous()` | Previous URL | `Referer`, then session | No | Referer is per tab | Core |
| Laravel `session()->previousUrl()/previousRoute()` | Last GET page | Written by `StartSession` after response | No | No | Core |
| Livewire `Livewire::originalUrl()/originalPath()` | Page a component lives on | Signed snapshot memo | Yes | Yes | Core; path only, no route, no previous |
| Livewire `#[Url]` | Sync state to query string | JS history API + Referer | Yes | Yes | Core; state only |
| Livewire `wire:current` | Highlight active links | JS `window.location` | Yes | Yes | Core; client only |

## Is the problem already solved?

- **Current page during a Livewire update**: partially, by
  `Livewire::originalUrl()` (path only, no route, undocumented).
- **Previous page during a Livewire update**: **no**. The only package that
  answers it (`livewire-urls`) answers with the wrong tab's data whenever more
  than one tab is open, and counts redirects and reloads as navigation.

No other package in the ecosystem addresses this problem.
