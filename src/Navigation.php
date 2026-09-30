<?php

declare(strict_types=1);

namespace Syriable\Packages\LivewireNavigation;

use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use WeakMap;

/**
 * Resolves the page the browser is displaying, and the page it displayed before,
 * regardless of whether the current request is a page load, a Livewire update
 * or any other request made from that page.
 */
final class Navigation
{
    /**
     * Session key holding the most recently recorded page visit.
     */
    public const string SESSION_KEY = 'livewire-navigation';

    /**
     * Livewire memo key holding the visit a component was rendered for.
     */
    public const string MEMO_KEY = 'navigation';

    /**
     * Visits read from verified Livewire snapshots, per request.
     *
     * @var WeakMap<Request, ?Visit>
     */
    private WeakMap $snapshotVisits;

    /**
     * Visits resolved for page requests, per request.
     *
     * @var WeakMap<Request, Visit>
     */
    private WeakMap $pageVisits;

    public function __construct(private readonly Container $container)
    {
        $this->snapshotVisits = new WeakMap;
        $this->pageVisits = new WeakMap;
    }

    /**
     * The full URL of the page the browser is displaying.
     */
    public function currentUrl(?string $fallback = null): ?string
    {
        return $this->visit()?->current->url ?? $fallback;
    }

    /**
     * The route name of the page the browser is displaying.
     */
    public function currentRoute(?string $fallback = null): ?string
    {
        return $this->visit()?->current->route ?? $fallback;
    }

    /**
     * The full URL of the page the browser displayed before the current one.
     */
    public function previousUrl(?string $fallback = null): ?string
    {
        return $this->visit()?->previous->url ?? $fallback;
    }

    /**
     * The route name of the page the browser displayed before the current one.
     */
    public function previousRoute(?string $fallback = null): ?string
    {
        return $this->visit()?->previous->route ?? $fallback;
    }

    /**
     * Determine whether the request asks the server for a page to display.
     *
     * @internal
     */
    public function isPageRequest(Request $request): bool
    {
        return $request->isMethod('GET')
            && $request->route() instanceof Route
            && ! $request->ajax()
            && ! $request->prefetch();
    }

    /**
     * Store the visit of the given page request as the latest one in the session.
     *
     * @internal
     */
    public function recordVisit(Request $request): void
    {
        $request->session()->put(self::SESSION_KEY, $this->pageVisit($request)->toArray());
    }

    /**
     * Remember the visit carried by a Livewire snapshot that passed checksum verification.
     *
     * @internal
     *
     * @param  array<array-key, mixed>  $snapshot
     */
    public function rememberSnapshot(array $snapshot): void
    {
        $memo = is_array($snapshot['memo'] ?? null) ? $snapshot['memo'] : [];

        $this->snapshotVisits[$this->request()] = Visit::fromArray($memo[self::MEMO_KEY] ?? null);
    }

    /**
     * The visit to embed in the memo of a Livewire component being dehydrated.
     *
     * @internal
     */
    public function visitForMemo(): ?Visit
    {
        $request = $this->request();

        if ($this->isPageRequest($request)) {
            return $this->pageVisit($request);
        }

        return $this->snapshotVisits[$request] ?? null;
    }

    private function visit(): ?Visit
    {
        $request = $this->request();

        if (($visit = $this->snapshotVisits[$request] ?? null) !== null) {
            return $this->withLiveQueryString($visit, $request);
        }

        if ($this->isPageRequest($request)) {
            return $this->pageVisit($request);
        }

        return $this->storedVisit($request);
    }

    private function pageVisit(Request $request): Visit
    {
        if (! $this->pageVisits->offsetExists($request)) {
            $route = $request->route();

            $page = new Page(
                $this->normalize($request->fullUrl()),
                $route instanceof Route ? $route->getName() : null,
            );

            $this->pageVisits[$request] = new Visit($page, $this->previousPage($page, $request));
        }

        return $this->pageVisits[$request];
    }

    /**
     * Resolve the page displayed before the given one.
     *
     * A same-origin Referer header identifies the page of the browser tab that
     * made the request. Without one, the latest visit recorded in the session
     * is used. Reloading or re-requesting the same page keeps its previous page.
     */
    private function previousPage(Page $page, Request $request): ?Page
    {
        $stored = $this->storedVisit($request);
        $referer = $this->sameOriginReferer($request);

        if ($referer !== null && $referer !== $page->url) {
            return $stored?->current->url === $referer
                ? $stored->current
                : new Page($referer, $this->routeNameFor($referer));
        }

        if (! $stored instanceof Visit) {
            return null;
        }

        return $stored->current->url === $page->url ? $stored->previous : $stored->current;
    }

    /**
     * During a Livewire update the Referer header holds the live browser URL,
     * including query string changes made after the page was loaded. It is
     * only trusted when it points at the same path as the signed snapshot.
     */
    private function withLiveQueryString(Visit $visit, Request $request): Visit
    {
        $referer = $this->sameOriginReferer($request);

        if ($referer === null || Str::before($referer, '?') !== Str::before($visit->current->url, '?')) {
            return $visit;
        }

        return new Visit(new Page($referer, $visit->current->route), $visit->previous);
    }

    private function storedVisit(Request $request): ?Visit
    {
        return $request->hasSession()
            ? Visit::fromArray($request->session()->get(self::SESSION_KEY))
            : null;
    }

    private function sameOriginReferer(Request $request): ?string
    {
        $referer = $request->headers->get('referer');

        if ($referer === null || $referer === '') {
            return null;
        }

        $root = rtrim($request->root(), '/');

        if ($referer !== $root && ! str_starts_with($referer, $root.'/') && ! str_starts_with($referer, $root.'?')) {
            return null;
        }

        return $this->normalize($referer);
    }

    private function routeNameFor(string $url): ?string
    {
        try {
            return $this->container->make(Router::class)->getRoutes()->match(Request::create($url))->getName();
        } catch (HttpExceptionInterface) {
            return null;
        }
    }

    /**
     * Bring a URL into the shape of Request::fullUrl(): no fragment, no
     * trailing slash and a normalized query string.
     */
    private function normalize(string $url): string
    {
        $url = Str::before($url, '#');
        $base = rtrim(Str::before($url, '?'), '/');
        $query = Request::normalizeQueryString(Str::contains($url, '?') ? Str::after($url, '?') : null);

        return $query === '' ? $base : $base.'?'.$query;
    }

    private function request(): Request
    {
        return $this->container->make('request');
    }
}
