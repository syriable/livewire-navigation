# 01 — Original package analysis: `ralphjsmit/livewire-urls`

Analysed version: **1.7.0** (commit `bf22a0c`, 2026-03-12), cloned from
`github.com/ralphjsmit/livewire-urls`. Every statement below comes from its
source, tests or git history. Nothing is inferred from the README alone.

## Inventory

| File | Lines | Role |
| --- | --- | --- |
| `src/Middleware/LivewireUrlsMiddleware.php` | 57 | Writes URL/route state to the session |
| `src/Url.php` | 55 | Reads the state back |
| `src/Facades/Url.php` | 23 | Facade over `Url` |
| `src/LivewireUrlsServiceProvider.php` | 17 | spatie/laravel-package-tools provider; registers an **empty** config file and an **empty** views directory |
| `config/livewire-urls.php` | 5 | `return [ // ];` |
| `tests/Feature/UrlTest.php` | 137 | The whole test suite (5 tests) |

Runtime dependencies: `php ^8.0`, `illuminate/contracts ^9–^13`,
`livewire/livewire ^2.10|^3|^4`, `spatie/laravel-package-tools`.

## A. Architecture map (as implemented)

```text
HTTP request
  │
  ▼
LivewireUrlsMiddleware::handle()          (user adds it to the "web" group by hand)
  │  isLivewireRequest()?  → LivewireManager::isLivewireRequest() (X-Livewire header) → skip
  │  method !== GET?       → skip
  │
  │  BEFORE the controller runs:
  │    session[livewire-urls.previous]       = session[livewire-urls.current]
  │    session[livewire-urls.previous-route] = session[livewire-urls.current-route]
  │    session[livewire-urls.current]        = $request->fullUrl()
  │    session[livewire-urls.current-route]  = $request->route()?->getName()
  │    push fullUrl / route name onto livewire-urls.history(-route), trim to 20
  ▼
$next($request)                           (response is never inspected)

Later, anywhere:
Url facade → Url::current()/previous()/currentRoute()/previousRoute()
          → session()->get('livewire-urls.*', $fallback)
Url::lastRecorded()/lastRecordedRoute()
          → newest history entry that differs from current
```

There is no Livewire integration beyond the header check. The package never
reads the Livewire snapshot and never touches the component lifecycle.

## B. Behavioural specification (verified)

| Question | Behaviour | Evidence |
| --- | --- | --- |
| What is the current URL? | `$request->fullUrl()` of the **most recent GET request that passed through the middleware**, session-wide | `LivewireUrlsMiddleware.php:22` |
| What is the previous URL? | The current URL before the most recent GET overwrote it | `:19` |
| When does the session update? | Every GET request that is not a Livewire request, **before** the controller runs | `:13-25` |
| When does it not update? | Livewire requests (X-Livewire header) and non-GET methods | `:13-19`, test "will exclude all request types apart from GET" |
| Livewire requests | Ignored; readers get whatever the last GET stored | `:35-38`, `UrlTest.php:39-50` |
| Redirects | The redirecting GET **is** recorded (response is not inspected), then the destination is recorded | `:27` runs `$next` last |
| First request | `previous` = null (session empty) | `UrlTest.php:17-22` |
| No route name | `currentRoute()` = null | `UrlTest.php:62-72` |
| Route parameters | Part of `fullUrl()`; not stored separately | `:22` |
| Query parameters | Included since 1.1.2 (`url()` → `fullUrl()`) | commit `92adeff` |
| Fragments | Never seen by the server, never stored | HTTP semantics |
| POST/PUT/PATCH/DELETE | Not recorded | `:40-43`, `UrlTest.php:113-137` |
| Validation errors | The redirect-back GET is recorded again, so `previous` becomes the same page | follows from `:19-22` |
| Reloading a page | `previous` becomes the same URL as `current` | `UrlTest.php:52-58` asserts `previous === test-b` after visiting `test-b` twice |
| JSON / AJAX / downloads / 404 / errors | All recorded if GET and the route exists (404 of unknown route: route middleware never runs) | response is never inspected |
| Fallbacks | Every reader takes `?string $fallback` | `Url.php`, `UrlTest.php:92-111` |
| `lastRecorded()` | Newest history URL different from `current` — exists because reloads pollute `previous` | `Url.php:28-37`, README |

## C. Weaknesses

**Architecture**
- State is **session-global**. With two tabs, a Livewire request from tab 1
  reads the URL of whatever tab loaded a page last. There is no way to fix
  this with a session alone.
- Recording happens **before** the response exists, so redirects, error pages,
  JSON endpoints and downloads become "pages the user visited".
- `previous` is corrupted by reloads and validation redirect-backs, which
  forced a second API (`lastRecorded*`) and a 20-entry history kept in every
  session forever.
- Manual middleware registration; forgetting it silently returns `null`.

**Dependencies / dead weight**
- `spatie/laravel-package-tools` used to register an empty config file and an
  empty views directory (`hasConfigFile()->hasViews()`).
- The history arrays are written on every page view but only read by
  `lastRecorded*`.

**Livewire assumptions**
- Detection is header-based only; the package never uses the signed
  component snapshot, which (since Livewire 3) already records the page each
  component was rendered on (see `02-livewire-analysis.md`).

**Testing gaps**
- The "Livewire request" assertion (`UrlTest.php:39-50`) POSTs to the
  Livewire route **without the package middleware on it and without an
  `X-Livewire` header**; it passes whether or not Livewire detection works.
- No tests for redirects, multiple tabs, JSON responses, sessions without
  middleware, query strings, or real Livewire component updates.

**API**
- `current()` is easily confused with Laravel's `url()->current()`.
- Six public methods where four carry the actual concept.
