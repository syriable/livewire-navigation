# Edge cases and security

"Recorded" means the request becomes the session's latest visit. "Test"
names the file in `tests/Feature` that verifies the row.

## Navigation

| Case | Expected behaviour | Test |
| --- | --- | --- |
| GET page (2xx, `text/html`) | Recorded | PageVisitTest |
| POST/PUT/PATCH/DELETE | Not recorded; current = page it was sent from | RecordingRulesTest |
| HEAD | Not recorded | RecordingRulesTest |
| Redirect response | Not recorded; destination is, with the page before the redirect as previous | RecordingRulesTest |
| `redirect()->back()` after failed validation | Page reloaded; previous kept | RecordingRulesTest |
| Successful form POST → redirect | Destination's previous = the form page | RecordingRulesTest |

## Livewire

| Case | Expected behaviour | Test |
| --- | --- | --- |
| Initial render (full page or embedded) | current = the request, previous resolved | PageVisitTest |
| Component update | current/previous from the signed snapshot; nothing recorded | LivewireUpdateTest |
| Nested component, updated alone | Same page context as its parent | LivewireUpdateTest |
| Component first rendered during an update | Inherits the context of the component being updated | LivewireUpdateTest |
| Lazy / polling / island updates | Ordinary updates of a snapshot rendered with the page → same as above | covered by the update path |
| `wire:navigate` | Plain GET → recorded like any page | PageVisitTest |
| `wire:navigate` prefetch never clicked | Session fallback may record it (indistinguishable server side). Pages actually displayed carry their own context, so Livewire updates stay correct | documented limitation |
| Browser back/forward via `wire:navigate` cache | No request; restored components still carry their own context | follows from memo design |
| Snapshot rendered before the package was installed | Falls back to the session | LivewireUpdateTest |
| Tampered memo | Rejected by Livewire's checksum | LivewireUpdateTest |
| `@persist` component | Reports the page it was first rendered on | documented limitation |

## Session

| Case | Expected behaviour | Test |
| --- | --- | --- |
| Route without session | Nothing recorded, no error; Referer still used | IntegrationTest |
| Expired / empty session | previous = Referer or null | PageVisitTest |
| Malformed session data | Ignored | IntegrationTest |
| Multiple tabs | Page requests use each tab's `Referer`; Livewire updates use each component's snapshot | LivewireUpdateTest |
| Concurrent requests | Last page response wins the session slot; only affects the fallback | by design |

## URLs

| Case | Expected behaviour | Test |
| --- | --- | --- |
| Query string | Included, normalized like `fullUrl()` | UrlShapeTest |
| Query changed by `#[Url]` after load | Reflected in `currentUrl()` during updates (from `Referer`, same path only) | LivewireUpdateTest |
| Encoded parameters | Preserved | UrlShapeTest |
| Route parameters | Part of the URL | PageVisitTest |
| Trailing slash | Removed, like `fullUrl()` | UrlShapeTest |
| Fragment | Never sent to servers; stripped if present in a header | UrlShapeTest |
| Signed / temporary URLs | Stored verbatim including signature | UrlShapeTest |
| HTTPS / ports | Part of the URL and of the same-origin check | UrlShapeTest |
| Subdirectory installs | Same-origin check uses `Request::root()` (includes base path) | by construction |
| Locale prefixes | Ordinary path segments | — |

## Routes

| Case | Expected behaviour | Test |
| --- | --- | --- |
| Named route | Name returned | PageVisitTest |
| Unnamed route | `null` | PageVisitTest |
| Referer never recorded | Route name resolved by matching the URL (GET) | PageVisitTest |
| Referer matching no route | route `null` | UrlShapeTest |
| Missing route (404) | Route middleware never runs → not recorded | RecordingRulesTest |
| `abort(403)` / exception | Not 2xx → not recorded | RecordingRulesTest |
| JSON, plain text, download | Not `text/html` or `attachment` → not recorded | RecordingRulesTest |
| AJAX (`X-Requested-With`) / prefetch headers | Not recorded | RecordingRulesTest |

## Security

- **Open redirects.** A cross-origin `Referer` is never used, so
  `redirect(Navigation::previousUrl())` can only point inside the
  application. The check compares against `Request::root()` followed by `/`
  or `?`, so `https://app.test.evil.com` does not pass for `https://app.test`.
- **Forgery.** The page context of a Livewire update comes from the snapshot
  memo, covered by Livewire's HMAC checksum. The `Referer` may only change the
  query string, and only when its path equals the signed path — a user can
  already type any query string into their own address bar.
- **Information exposure.** The memo embeds the current and previous URL of
  the user's own navigation in the page HTML. The browser already knows both
  (`location`, `document.referrer`). Applications that put secrets in URLs
  should be aware the previous URL is included.
- **Session growth.** One fixed-size entry (four strings) per session.
