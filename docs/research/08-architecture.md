# 08 — Architecture

Includes the minimalism review (track 13) and compatibility decision (track 11).

## Classes

```text
src/
  LivewireNavigationServiceProvider.php   binds the service, appends the middleware to "web",
                                           registers two Livewire listeners
  Navigation.php                          the service: resolution rules + public API
  Http/Middleware/TrackNavigation.php     records displayed pages after the response
  Facades/Navigation.php                  static access
  Page.php                    @internal   (url, route) pair
  Visit.php                   @internal   (current Page, previous Page); the unit stored in
                                           the session and in the component memo
```

No contracts, repositories, managers, events, config file, views, or
JavaScript.

## Data flow

```text
GET page P  ──► controller / Livewire mount
                 │  Navigation::* resolves:
                 │    current  = request fullUrl + route name
                 │    previous = same-origin Referer ─► else session visit
                 │  Livewire "dehydrate" → memo.navigation = Visit(P, previous)   (signed)
                 ▼
               response 2xx text/html, not an attachment
                 │
               TrackNavigation → session[livewire-navigation] = Visit(P, previous)

POST /livewire/update (component rendered on P)
               Livewire "snapshot-verified" → remember memo.navigation for this request
                 │  Navigation::* resolves:
                 │    current  = memo url (query from Referer if same path) + memo route
                 │    previous = memo previous
                 │  "dehydrate" → carry the same memo to the outgoing snapshots
                 ▼
               nothing recorded

Any other request (form POST, fetch, …)
               Navigation::* resolves from session[livewire-navigation]
```

## Minimalism review

| Question | Answer |
| --- | --- |
| Can Laravel already do this? | No: one session slot, not per tab; `url()->previous()` changes meaning inside Livewire (see 03). |
| Can Livewire already do this? | Only the current *path* (see 02). |
| Do we need middleware? | Yes. Writing the session must happen inside `StartSession`, after the response is known. |
| Do we need a facade? | Kept: it is the idiomatic call-site for a request-scoped read in components; one 20-line file. |
| Do we need configuration? | No. Nothing has a sensible second value. Removed. |
| Do we need a service provider? | Yes, for auto-registration; a plain `ServiceProvider`, no package-tools dependency. |
| Session abstraction? | No; `$request->session()` directly. |
| JavaScript? | No; the snapshot memo and `Referer` already carry per-tab context. |
| Value objects? | Two tiny internal ones, because the same (url, route, previous) tuple travels through session, memo and resolution. |
| Can this be one middleware + one service? | Yes — that is the design, plus the provider and facade. |

## Lifetimes

- `Navigation` is bound `scoped`, so Octane and queue workers get a fresh
  instance per request/job. Per-request state lives in `WeakMap`s keyed by
  the `Request` object, so it can never leak into another request even when a
  container is reused (as in HTTP tests).
- Livewire listeners resolve the service through the global container at call
  time, never through a captured instance.

## Compatibility decision

| | Status | Reason |
| --- | --- | --- |
| PHP 8.4+ | Required | Target stack; typed class constants, readonly classes |
| Laravel 13 | Required | Target |
| Livewire 4 | Required | Target |
| Laravel 12 | Supported | Identical APIs used; full suite passes on 12.69.3 |
| Livewire 3.6+ | Supported | Identical hooks (`dehydrate`, `snapshot-verified`, memo); full suite passes on 3.8.10 |
| Laravel ≤ 11, Livewire 2, PHP < 8.4 | Unsupported | No memo in Livewire 2; outside the target stack |

Composer: `php ^8.4`, `illuminate/{http,routing,support} ^12.0|^13.0`,
`livewire/livewire ^3.6|^4.0`.
