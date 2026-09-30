# Livewire Navigation

Know which page the user is on, and which page they came from — inside
Livewire requests, per browser tab.

```php
use Syriable\Packages\LivewireNavigation\Facades\Navigation;

public function cancel(): void
{
    $this->redirect(Navigation::previousUrl(route('dashboard')), navigate: true);
}
```

## The problem

A browser shows a page. A Livewire component on that page sends requests to
Livewire's own endpoint. While handling those requests, Laravel describes the
endpoint, not the page:

| | Page request (`GET /users?role=admin`) | Livewire update from that page |
| --- | --- | --- |
| `url()->current()` | `/users` | `/livewire/update` |
| `request()->route()` | `users.index` | `default-livewire.update` |
| `url()->previous()` | the page before | **the current page** (Referer) |
| `session()->previousRoute()` | the page before | the last page loaded **in any tab** |

There are three different URLs involved:

- **Browser URL** — what the address bar shows, including query string
  changes made by `#[Url]` after the page loaded.
- **Request URL** — the URL of the request being handled.
- **Livewire request URL** — Livewire's update endpoint, the request URL of
  every component interaction.

Livewire remembers the *path* of the page a component was rendered on
(`Livewire::originalPath()`), but not its query string, its route, or the
page before it. This package fills exactly that gap.

## Installation

```bash
composer require syriable/livewire-navigation
```

That is all. The service provider is auto-discovered and appends the
`TrackNavigation` middleware to the `web` middleware group. There is no
configuration file.

If your pages use a middleware group other than `web`, add the middleware to
it yourself, after `StartSession`:

```php
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->appendToGroup('portal', \Syriable\Packages\LivewireNavigation\Http\Middleware\TrackNavigation::class);
})
```

Requires PHP 8.4+, Laravel 12 or 13, and Livewire 3.6+ or 4.

## Usage

```php
use Syriable\Packages\LivewireNavigation\Facades\Navigation;

Navigation::currentUrl();     // "https://app.test/users?role=admin"
Navigation::currentRoute();   // "users.index"
Navigation::previousUrl();    // "https://app.test/dashboard"
Navigation::previousRoute();  // "dashboard"
```

Every method returns `null` when the value is unknown and accepts a fallback:

```php
Navigation::previousUrl(route('dashboard'));
Navigation::previousRoute('dashboard');
```

Prefer dependency injection? Type-hint the service instead:

```php
use Syriable\Packages\LivewireNavigation\Navigation;

public function save(Navigation $navigation): void
{
    // ...
    $this->redirect($navigation->currentUrl(), navigate: true);
}
```

The methods give the same answers during the page request, during every
Livewire update from that page, and during other requests sent from it such
as a form `POST`.

### Current page

The page the browser tab is displaying. During a Livewire update the URL
includes query string changes made after the page loaded (for example by
`#[Url]` properties), so redirecting to `Navigation::currentUrl()` keeps the
user's filters.

`currentRoute()` returns the route **name**, or `null` for an unnamed route.

### Previous page

The page the browser tab displayed before the current one.

- Reloading a page, or being redirected back to it after a failed validation,
  does not change its previous page.
- A redirect is never a page: after `/old` redirects to `/new`, the previous
  page of `/new` is the page before `/old`.
- JSON responses, downloads, error pages, AJAX and prefetch requests are never
  pages.
- The previous URL is always a URL of your own application, so it is safe to
  redirect to.

## How it works

1. **When a page renders**, the previous page is taken from the browser's
   same-origin `Referer` header (which is per tab), falling back to the last
   page recorded in the session.
2. The current and previous page are **embedded in the memo of every Livewire
   component** on that page. The memo is signed by Livewire's snapshot
   checksum, so it cannot be tampered with, and it belongs to that tab.
3. **During Livewire updates**, the values are read back from the memo of the
   component being updated. Components rendered during an update inherit
   them.
4. **After a page response** (a successful `text/html` response to a `GET`),
   the middleware stores the page in the session, which serves requests that
   carry no component, such as a plain form `POST`.

### `wire:navigate`

`wire:navigate` loads pages with a normal `GET`, so they are recorded like
any other page. Pages restored from `wire:navigate`'s history cache when
using back/forward keep the context their components were rendered with.

## Limitations

- **Hover prefetching.** `wire:navigate.hover` requests pages that may never
  be shown; the server cannot tell them apart from real visits. Components on
  pages that *are* shown still carry correct context; only the session
  fallback can be affected.
- **Persisted components.** A component kept alive with `@persist` reports
  the page it was first rendered on.
- **Fragments** (`#section`) never reach the server and are never included.
- **Referrer policy.** With `Referrer-Policy: no-referrer` the previous page
  falls back to the session, which is shared by all tabs. With a policy that
  sends only the origin, the previous page of a page request is reported as
  your application's root URL.
- **Components rendered before installation** fall back to the session until
  the page is reloaded.
- The previous URL is embedded in page HTML. Do not put secrets in URLs.

## Testing your application

Drive real requests, and send a `Referer` header where the browser would:

```php
$this->get('/dashboard');

$this->get('/users', ['Referer' => url('/dashboard')])
    ->assertSee('Back to dashboard');
```

`Livewire::test()` does not perform a page request, so inside it the values
come from the session.

## Documentation

- [Architecture](docs/architecture.md) — classes, data flow and design decisions
- [Edge cases and security](docs/edge-cases.md) — expected behaviour for every case
- [Livewire internals](docs/livewire-internals.md) — what Livewire tracks about the page
- [Laravel request lifecycle](docs/request-lifecycle.md) — why Laravel's helpers change meaning
- [Testing](docs/testing.md) — how the test suite simulates the browser
- [Non-goals](docs/non-goals.md) — what the package deliberately leaves out

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## Development

```bash
composer test       # Pest
composer analyse    # PHPStan (Larastan, level max)
composer refactor   # Rector (dry run)
composer format     # Pint (test mode)
composer check      # all of the above
```

## License

The MIT License. See [LICENSE.md](LICENSE.md).
