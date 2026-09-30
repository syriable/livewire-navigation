# 12 — Final specification

## Decision

```text
BUILD
```

Laravel keeps one "previous URL" per session and redefines it inside
Livewire requests; Livewire knows only the current *path* of a component.
Neither can say which page a browser tab showed before the current one, nor
the full URL and route of the current page during a Livewire update. The
package provides exactly that, per browser tab, without JavaScript.

## Architecture synthesis

**Original package does correctly:** records only GET requests; skips
Livewire requests; stores the full URL and route name; offers fallbacks.

**Original package does poorly:** session-global state (wrong in multiple
tabs); records redirects, JSON, downloads and error pages; reloads overwrite
`previous` (hence `lastRecorded`); manual registration; unused
dependencies/config; untested Livewire path.

**Livewire solves natively:** detecting its requests, the signed page path of
each component (`originalUrl()`), query-string state, active links, SPA
navigation.

**Laravel solves natively:** the current request's URL and route; a single
session-wide last-GET URL/route; Referer-based `url()->previous()`.

**Unsolved:** the previous page, per tab; the current page's route and live
query string during Livewire updates.

**We solve:** those two things, and nothing else.

**We explicitly do not solve:** see `11-do-not-build.md`.

## Specification

| Item | Value |
| --- | --- |
| PACKAGE NAME | `syriable/livewire-navigation` |
| PACKAGE PURPOSE | Current and previous page (URL + route name) that work identically in page requests, Livewire updates and other requests, per browser tab |
| SUPPORTED PHP | ^8.4 |
| SUPPORTED LARAVEL | ^12.0, ^13.0 |
| SUPPORTED LIVEWIRE | ^3.6, ^4.0 |
| PUBLIC API | `Navigation::currentUrl()`, `currentRoute()`, `previousUrl()`, `previousRoute()`, each with `?string $fallback = null` (facade or injected service) |
| ARCHITECTURE | Service provider, `Navigation` service, `TrackNavigation` middleware, facade, two internal value objects (08) |
| REQUEST LIFECYCLE | Page requests resolve from the request; Livewire updates from the signed memo; everything else from the session (08) |
| SESSION STRATEGY | One key, `livewire-navigation`, holding `{url, route, previous_url, previous_route}` of the latest displayed page; written after the response; used as fallback only |
| MIDDLEWARE STRATEGY | Auto-appended to the `web` group (after `StartSession`); records GET + route + not AJAX + not prefetch + 2xx + `text/html` + not attachment |
| LIVEWIRE DETECTION STRATEGY | Not header-based: a request is treated as a Livewire update when a verified snapshot carrying the memo was hydrated (`snapshot-verified`) |
| URL STORAGE STRATEGY | `fullUrl()` form: absolute, normalized query, no trailing slash, no fragment; the `Referer` is normalized to the same form |
| ROUTE STORAGE STRATEGY | Route name only; for an unseen `Referer` the name is resolved by matching a GET request for it |
| EDGE CASE BEHAVIOR | `06-edge-cases.md` |
| TEST STRATEGY | `09-test-strategy.md` |
| DEPENDENCIES | `illuminate/http`, `illuminate/routing`, `illuminate/support`, `livewire/livewire` |
| NON-GOALS | `11-do-not-build.md` |

### Resolution rules

Previous page while rendering page P:

1. A same-origin `Referer` that is not P itself → that URL (route name from
   the session if it is the recorded page, otherwise matched).
2. Else, if the session's latest visit is P (reload / redirect-back) → its
   previous page.
3. Else the session's latest visit, or `null`.

Current page during a Livewire update: the memo's URL; if a same-origin
`Referer` has the same path, the `Referer` (to pick up query changes).

## Old vs new

| Concern | Original package | Modern approach | Evidence |
| --- | --- | --- | --- |
| Current URL | Last GET in the session | Page request: the request. Livewire: signed memo (+ live query). Other: session | `LivewireUrlsMiddleware.php:22`; `Navigation::visit()`; LivewireUpdateTest |
| Previous URL | Session value shifted on every GET, including reloads | Per-tab Referer, else session; reloads keep it | `:19`; RegressionTest "reload" |
| Route | Route name in session | Route name in memo/session; matched for unseen Referers | PageVisitTest |
| Livewire detection | `X-Livewire` header | Verified snapshot memo | `HandleRequests.php:119-122`; `HandleComponents.php:250` |
| Middleware | Manual, records before the response | Auto-registered in `web`, records after a displayed HTML response | IntegrationTest, RecordingRulesTest |
| Session | 6 keys + 2 history arrays of 20 | 1 key, 4 strings, fallback only | `LivewireUrlsMiddleware.php:19-30` |
| Navigation | Every GET incl. redirects/JSON/errors | Only displayed pages | RegressionTest |
| `wire:navigate` | Recorded as GET; wrong after cached back/forward | Recorded as GET; restored components keep their own context | `js/request/index.js:666-676`; `history.js:92-130` |
| Livewire 4 | constraint bump only (commit `79c7e84`) | Uses the v3/v4 memo and event bus | `PersistentMiddleware.php:33-39` |
| Laravel 13 | constraint bump only (commit `88b40c5`) | Tested on 13.34 and 12.69 | CI matrix |

## Regression against the original package

`tests/Feature/RegressionTest.php` runs both packages on the same requests.
Identical: consecutive visits, unnamed routes, non-GET requests, Livewire
updates. Intentionally different (each asserted): reloads, redirects, non-HTML
responses, multiple tabs — all cases where the original reports a page the
user did not navigate from.
