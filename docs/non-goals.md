# Non-goals

What this package deliberately does not do, and why.

## Already provided by Laravel

- The URL/route of the current request — `request()->fullUrl()`, `request()->route()`.
- "Go back" for plain forms — `redirect()->back()`.
- Post-login return URLs — `redirect()->intended()`.
- Detecting AJAX / prefetch / precognition — `Request` methods.

## Already provided by Livewire

- Query-string state — `#[Url]`. We never parse or expose individual query parameters.
- Active-link styling — `wire:current`.
- Detecting Livewire requests — `Livewire::isLivewireRequest()`.
- The page *path* of a component — `Livewire::originalPath()`.
- SPA navigation, prefetching, history — `wire:navigate`.

## Rejected for this package

| Idea | Why not |
| --- | --- |
| JavaScript / tab IDs | Snapshot memo + `Referer` already give per-tab context |
| Config file | No option has a meaningful second value |
| Events (`PageVisited`) | No use case; listeners can use Laravel middleware |
| History of N pages | The previous page survives reloads and redirects, so one step back is enough |
| Route parameters / route objects | Recoverable with `Route::getRoutes()->match()` on the URL when needed |
| Contracts / repositories / storage drivers | One implementation, one store |
| Blade directives, helpers | Facade and DI are enough |
| Livewire 2 / Laravel ≤ 11 support | No memo in Livewire 2; outside target stack |
| A testing fake | Tests can drive real requests; the rules are cheap to exercise |

## Future features (evaluated, not built)

| Feature | Extension point if ever needed |
| --- | --- |
| Multiple previous pages | `Visit` becomes a bounded list; memo/session shape versioned |
| Previous route parameters | Match the stored URL at read time |
| Navigation events | Dispatch from `TrackNavigation` |
| Route metadata | Derive from the route name |
| Tab-specific history | Would need JavaScript; out of scope |
