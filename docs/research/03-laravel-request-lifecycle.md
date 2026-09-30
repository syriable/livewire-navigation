# 03 — Laravel request lifecycle

Source inspected: **laravel/framework 13.34.0** (12.69.3 for comparison; the
relevant code is identical).

## Lifecycle of a web request

```text
public/index.php → Application::handleRequest()
  → Http\Kernel::handle()
      global middleware
      Router::dispatch() → route matched → Route middleware:
        "web" group (order matters):
          EncryptCookies
          AddQueuedCookiesToResponse
          StartSession ──────────────┐ session loaded
          ShareErrorsFromSession     │
          PreventRequestForgery      │  (CSRF; ValidateCsrfToken in Laravel 12)
          SubstituteBindings         │
          …appended middleware…      │  ← a package middleware appended to "web" runs here
          controller / Livewire      │
          Router::toResponse()       │  → $response->prepare($request): Content-Type defaults to text/html
          ◄── response ──────────────┤     (Routing/Router.php:946, before route middleware see it)
        StartSession::storeCurrentUrl()  (after the response exists)
        StartSession::saveSession()      (session written)
  → Kernel::terminate()
```

A middleware that wants to write the session must run **inside**
`StartSession`, i.e. after it in the `web` group. Anything that runs after
`Kernel::handle()` returns (e.g. the `RequestHandled` event) is too late: the
session is already saved.

## What Laravel exposes

| API | Returns | Source |
| --- | --- | --- |
| `request()->url()` / `url()->current()` | URL of *this* request, no query | `Illuminate/Http/Request.php`, `Routing/UrlGenerator.php` |
| `request()->fullUrl()` | URL of *this* request with a normalized (sorted) query | `Illuminate/Http/Request.php` |
| `request()->route()` | Route matched for *this* request | route resolver set by the router |
| `url()->previous()` | **`Referer` header first**, then `session('_previous.url')`, then `/` | `Routing/UrlGenerator.php:162-175` |
| `url()->previousPath()` | Path of the above | `:183-196` |
| `session()->previousUrl()` / `previousRoute()` | `_previous.url` / `_previous.route` | `Session/Store.php:791-825` |
| `redirect()->back()` | `url()->previous()` | `Routing/Redirector.php` |

`_previous.url` / `_previous.route` are written by
`StartSession::storeCurrentUrl()` (`Session/Middleware/StartSession.php:199-212`)
**after** the response is built, only when the request is `GET`, has a
route, is not `ajax()`, not `prefetch()` and not precognitive. Redirects,
JSON responses and error pages of routed GET requests are recorded.

## Why this is insufficient during a Livewire request

| Question asked during `POST /livewire/update` | Laravel's answer | Correct answer |
| --- | --- | --- |
| Current page URL | `url()->current()` = `/livewire/update` | the page showing the component |
| Current route | `request()->route()` = `default-livewire.update` | the page's route |
| Previous page URL | `url()->previous()` = `Referer` = **the current page** | the page before it |
| Previous route | `session()->previousRoute()` = the last GET of **any tab** — usually the current page | the page before it |

The same helper (`url()->previous()`) means "the page before this one" in a
page request and "this page" in a Livewire request. Laravel stores exactly one
URL per session, so it has no slot for "the page before the current page",
and a session value cannot distinguish browser tabs.

## Other facts relevant to the design

- `Request::prefetch()` checks `Purpose`/`Sec-Purpose: prefetch` and
  `X-Moz: prefetch` only (`Http/Request.php:319-324`); `wire:navigate`
  prefetches send none of these.
- `Request::normalizeQueryString()` (Symfony) sorts query parameters; a URL
  taken from a header must be normalized the same way before it can be
  compared to `fullUrl()`.
- `Request::root()` includes scheme, host, port and base path, so it is the
  right prefix for a same-origin check (subdirectory installs included).
- `Http\Kernel::appendMiddlewareToGroup()` appends idempotently and re-syncs
  the router, so a package can register itself in `web` without touching
  `bootstrap/app.php`.
