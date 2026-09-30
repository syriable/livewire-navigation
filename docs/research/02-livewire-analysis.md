# 02 — Livewire analysis

Source inspected: **livewire/livewire 4.4.7** (and 3.8.10 for comparison).
Paths are relative to the package root.

## Three different URLs

| Concept | Example | Where it exists in Livewire |
| --- | --- | --- |
| **State URL** — query string bound to component properties | `/users?search=john` | `#[Url]` / `SupportQueryString`. Written to the browser with `history.replaceState/pushState` (`js/features/supportQueryString.js:44`, `js/plugins/history/coordinator.js`). No server request. |
| **Browser page URL** — what the address bar shows | `https://app.test/dashboard/users?search=john` | Only in the browser (`window.location`). On the server: the `Referer` header of later requests, and the page path stored in each component snapshot. |
| **Livewire request URL** | `POST /livewire-<hash>/update` | `HandleRequests::boot()` registers `Route::post($path)->middleware(['web', RequireLivewireHeaders::class])->name('default-livewire.update')` (`src/Mechanisms/HandleRequests/HandleRequests.php:23-31`). |

During a component update, `request()->url()`, `url()->current()` and
`request()->route()` describe the **third** URL.

## What Livewire already solves

1. **Detecting its own requests.**
   `Livewire::isLivewireRequest()` → `request()->hasHeader('X-Livewire')`
   (`HandleRequests.php:119-122`); every update is a `POST` with that header
   (`js/request/index.js:309-315`).

2. **Remembering the page a component was rendered on.**
   `PersistentMiddleware` adds `memo.path` and `memo.method` to every
   component snapshot on dehydrate, and carries them forward on later updates
   (`src/Mechanisms/PersistentMiddleware/PersistentMiddleware.php:33-39, 80-97`).
   The snapshot is HMAC-signed with the app key
   (`src/Mechanisms/HandleComponents/Checksum.php:81-89`) and verified before
   hydration (`HandleComponents.php:246-250`), so the value cannot be forged.

3. **Exposing that page.**
   `Livewire::originalUrl()`, `originalPath()`, `originalMethod()`
   (`src/LivewireManager.php:298-326`) read `memo.path` from
   `components.0.snapshot`.
   Limitations: **path only** (`request()->path()`), so the query string is
   lost; no route name; the fallback for a missing path is the string
   `'POST'`; it only looks at the first component of the request.

4. **Re-running route middleware for the original route.**
   `PersistentMiddleware::makeFakeRequest()` rebuilds a request for
   `memo.path` and matches it against the router (`:146-199`) — but the
   matched route is internal and not exposed.

5. **The live query string.**
   `#[Url]` hydration during updates reads the **`Referer` header**, because
   the request URL is the Livewire endpoint
   (`src/Features/SupportQueryString/BaseUrl.php:163-196`).

6. **Client-side "am I on this page".** `wire:current` compares link hrefs to
   `window.location` in JavaScript (`js/directives/wire-current.js`).

7. **SPA navigation.** `wire:navigate` fetches the next page with a plain
   `GET` and an `X-Livewire-Navigate` header (`js/request/index.js:666-676`).
   To Laravel this is a normal page request: not `ajax()`, not `prefetch()`,
   not `X-Livewire`.

## What Livewire does **not** solve

- **Previous page.** Nothing in Livewire knows which page the browser showed
  before the current one.
- **Current route name during an update.** Not exposed anywhere.
- **Full current URL during an update.** `originalUrl()` drops the query
  string.
- **Per-component context beyond `path`/`method`.** Nothing else about the
  page is carried in the snapshot.

## Behaviour that affects any server-side tracking

- **Prefetching.** `wire:navigate` prefetches on `mousedown` (and on hover
  with `.hover`) using the *same* request as a real navigation
  (`js/plugins/navigate/prefetch.js`, `fetch.js`). The server cannot tell a
  prefetch from a visit. A prefetched page that is never clicked still looks
  like a visit to any session-based tracker.
- **Back/forward.** `wire:navigate` restores pages from its in-memory
  snapshot cache on `popstate` without any server request
  (`js/plugins/navigate/history.js:92-130`). A session-based tracker never
  learns about it. The restored HTML, however, contains the original
  component snapshots — so anything stored in the snapshot memo is still
  correct after back/forward.
- **`@persist`.** Persisted components keep the snapshot of the page where
  they were first rendered, including `memo.path`.
- **Redirects from components.** `$this->redirect($url, navigate: true)`
  makes the browser perform a normal or `wire:navigate` GET of `$url`
  (`src/Features/SupportRedirects/HandlesRedirects.php:9-18`); the Livewire
  request itself never becomes a page.

## Extension points for a package

`Livewire::listen()` (`LivewireManager.php:116-118`) exposes the internal
event bus. Two events are enough:

- `dehydrate($component, ComponentContext $context)` — `$context->addMemo()`
  adds signed data to the snapshot (`HandleComponents.php:233`).
- `snapshot-verified(array $snapshot)` — the checksum has passed
  (`HandleComponents.php:250`).

Both exist unchanged in Livewire 3.8 (`HandleComponents.php:133, 149`) and
are what Livewire's own `PersistentMiddleware` uses.

## Conclusion

Livewire solves "which *path* is this component on" and nothing about
"where did the user come from". It provides a signed, per-tab, per-component
storage slot (the memo) that a package can use to carry page context without
JavaScript.
