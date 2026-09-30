<?php

declare(strict_types=1);

namespace Syriable\Packages\LivewireNavigation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Syriable\Packages\LivewireNavigation\Navigation;

/**
 * Records every successfully displayed HTML page as the latest visit in the session.
 *
 * Livewire updates, redirects, error pages, JSON, downloads, AJAX and prefetch
 * requests are never recorded, because the browser does not display them as a page.
 */
final readonly class TrackNavigation
{
    public function __construct(private Navigation $navigation) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->hasSession() && $this->navigation->isPageRequest($request) && $this->isDisplayedPage($response)) {
            $this->navigation->recordVisit($request);
        }

        return $response;
    }

    private function isDisplayedPage(Response $response): bool
    {
        return $response->isSuccessful()
            && str_contains((string) $response->headers->get('Content-Type'), 'text/html')
            && ! str_starts_with(strtolower((string) $response->headers->get('Content-Disposition')), 'attachment');
    }
}
