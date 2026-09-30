# 07 — API design

## Usage the API must serve

Collected from the original README and the problem definition:

1. "Cancel"/"Back" after an action inside a component:
   `return $this->redirect(Navigation::previousUrl(route('dashboard')), navigate: true);`
2. Redirect to the page the component is on (keeping filters):
   `$this->redirect(Navigation::currentUrl())`.
3. Branch on where the user came from:
   `if (Navigation::previousRoute() === 'checkout.cart')`.
4. Highlight / authorize based on the current page from inside a nested
   component: `Navigation::currentRoute()`.

All four need a string, sometimes with a fallback. None needs an object.

## Options evaluated

| Option | Verdict |
| --- | --- |
| Keep `Url::current()/previous()` | Rejected: collides mentally with `url()->current()` / `url()->previous()`, which mean different things here. |
| `Navigation::current()->url()` value objects | Rejected: every use case needs one scalar; an object adds a hop and a nullable object check. |
| Helper function `navigation()` | Rejected: global namespace pollution; facade and DI already cover it. |
| `lastRecorded()` / history | Rejected: only existed to repair reload handling, which is now correct. |
| Injectable service + facade with 4 scalar methods | **Chosen** |

## Final public API

```php
use Syriable\Packages\LivewireNavigation\Facades\Navigation;

Navigation::currentUrl(?string $fallback = null): ?string
Navigation::currentRoute(?string $fallback = null): ?string
Navigation::previousUrl(?string $fallback = null): ?string
Navigation::previousRoute(?string $fallback = null): ?string
```

The same four methods are available on
`Syriable\Packages\LivewireNavigation\Navigation` for constructor or method
injection. Methods on that class marked `@internal` are used by the
middleware and the Livewire hooks and are not part of the public API.

Public constants `Navigation::SESSION_KEY` and `Navigation::MEMO_KEY` name
the storage slots, for applications that need to clear or inspect them.
