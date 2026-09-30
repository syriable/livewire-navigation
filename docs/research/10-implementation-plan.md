# 10 — Implementation plan

| Phase | Deliverable | Status |
| --- | --- | --- |
| 1 | Skeleton: `composer.json`, PSR-4, Pint, PHPStan (Larastan, max), Rector, Pest, CI | done |
| 2 | `LivewireNavigationServiceProvider` (scoped binding, `web` registration) | done |
| 3 | `Page`, `Visit` and session storage | done |
| 4 | `TrackNavigation` middleware (2xx, `text/html`, not attachment) | done |
| 5 | Livewire listeners: `dehydrate` writes the memo, `snapshot-verified` reads it | done |
| 6 | `currentUrl()` / `currentRoute()` incl. live query string | done |
| 7 | `previousUrl()` / `previousRoute()` incl. Referer + route matching | done |
| 8 | Route name resolution for unseen Referers | done |
| 9 | Normalization (query order, trailing slash, fragment), same-origin check | done |
| 10 | Feature suites listed in 09 | done |
| 11 | `composer check` (Pint, Rector, PHPStan, Pest) clean | done |
| 12 | README, research documents, changelog | done |

Quality gates: `composer test`, `vendor/bin/pest`,
`vendor/bin/phpstan analyse`, `vendor/bin/rector process --dry-run`,
`vendor/bin/pint --test`.
