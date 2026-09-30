# 05 — Problem definition

Includes the evolution timeline of the original package (research track 5).

## Formal statement

```text
Given
  a browser tab T displaying page P (URL u_P, served by route r_P),
  which T reached from page Q (URL u_Q, route r_Q, possibly none),

when
  T sends request R to the server that is not a request for a new page
  (a Livewire update, a form POST, a fetch),

then
  R's own URL and route do not describe P, and Laravel keeps no per-tab
  record of Q.

We need, while handling R:
  current  = (u_P, r_P)   — including query changes made in T after P loaded
  previous = (u_Q, r_Q)
and the same answers while P itself is being rendered.
```

## Concepts

| Concept | Definition | In the package? |
| --- | --- | --- |
| Browser URL | What T's address bar shows | Yes — "current URL" |
| Request URL | URL of R | No — Laravel already provides it |
| Livewire endpoint | `/livewire…/update` | Only to ignore it |
| Current page | Last page T displayed (a 2xx HTML response to a GET) | Yes |
| Previous page | Page T displayed before the current page | Yes |
| Current / previous route | Route **name** that served the page | Yes |
| Navigation | T displaying a new page | Recorded implicitly |
| Redirect | Response that is never displayed | Excluded |
| History (n > 1 pages) | — | No (see 11-do-not-build) |
| Session | Fallback store when a request carries no per-tab context | Yes, internal |
| Tab | Identity of T | Implicit: `Referer` + signed snapshot; no tab IDs |
| Component | Carrier of page context during updates | Yes, via memo |

## Timeline of `ralphjsmit/livewire-urls` (git history)

| Date | Version | Change | Why it matters |
| --- | --- | --- | --- |
| 2022-06-17 | 1.0 | Middleware + session + facade | Livewire 2 era |
| 2022-06-17 | 1.1 / 1.0.1 | `lastRecorded*`, 20-item history trim | Workaround for reloads polluting `previous` |
| 2022-07-14 | 1.1.2 | `url()` → `fullUrl()` | Query strings included |
| 2022-11-19 | 1.1.3 | GET only; fallback fix | POSTs were recorded as pages |
| 2023-02-17 | 1.2.0 | Laravel 10; `lastRecorded` logic fixed | |
| 2023-08-19 / 11-29 | 1.3.x | Livewire 3 | **composer.json only** |
| 2024-03-14 | 1.4.0 | Laravel 11; test finds the update route by several names | `livewire.message` (v2) → `livewire.update` / `default-livewire.update` (v3+) |
| 2025-02-25 | 1.5.0 | Laravel 12 | constraints only |
| 2026-01-27 | 1.6.0 | Livewire 4 | **composer.json only** |
| 2026-03-12 | 1.7.0 | Laravel 13 | constraints only |

The implementation has not changed since Livewire 2. Livewire 3 introduced
the signed `memo.path` (making per-tab context possible) and Livewire 3/4
introduced `wire:navigate` (prefetching, cached back/forward); the package
never adopted or accounted for either.

## Answer to the key research question

> Does a modern Livewire 4 application still need a package like
> `livewire-urls`, and if so, what is the smallest correct implementation?

Yes, for the **previous** page and for the **route/full URL** of the current
page during Livewire requests. Laravel answers neither, Livewire answers only
the current *path*. The smallest correct implementation is one middleware
(record displayed pages after the response) plus one service that reads the
page context from the signed Livewire snapshot, the `Referer` header, or the
session, in that order.
