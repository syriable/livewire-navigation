# Testing

## Principles

- Tests drive the package the way a browser does: real HTTP requests through
  the `web` middleware group, real Livewire components, and real Livewire
  update requests built from the snapshot found in the rendered HTML.
- Browser behaviour that the server cannot observe (tabs, `Referer`) is
  simulated with headers, exactly as it arrives in production.
- Assertions are on the four public values, never on internals — with one
  exception: the memo shape is asserted once, because it is a signed
  contract between two requests.
- Pest + Orchestra Testbench; the array session driver.

## Suites (`tests/Feature`)

| File | Category |
| --- | --- |
| `PageVisitTest` | Navigation, Session |
| `LivewireUpdateTest` | Livewire, Integration, Security |
| `RecordingRulesTest` | Middleware, Navigation |
| `UrlShapeTest` | URLs, Security |
| `IntegrationTest` | Service provider, Session |

Compatibility is covered by running the whole suite on every supported
Laravel × Livewire combination in CI.

## Behaviour matrix

| Scenario | current URL | previous URL |
| --- | --- | --- |
| First page A | A | null |
| A then B (no Referer) | B | A (session) |
| A, C, then B with Referer A | B | A (Referer beats session) |
| Reload B | B | unchanged |
| Livewire update on B | B | B's previous (memo) |
| Update on tab 1 (B) after tab 2 loaded C | B | A |
| Update with Referer `B?x=1` | `B?x=1` | memo previous |
| Update with Referer on another path / origin | B | memo previous |
| GET /redirect → B | B | page before the redirect |
| JSON / text / 403 / 500 / 404 / download / AJAX / prefetch / HEAD / non-GET | not recorded | unchanged |
| POST from B | B | B's previous (session) |
| Validation redirect back to B | B | unchanged |
| Unnamed route | URL, route null | — |
| No session | request / Referer only | Referer or null |
