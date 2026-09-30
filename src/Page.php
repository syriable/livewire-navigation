<?php

declare(strict_types=1);

namespace Syriable\Packages\LivewireNavigation;

/**
 * A page the browser displayed: its full URL and the name of the route that served it.
 *
 * @internal
 */
final readonly class Page
{
    public function __construct(
        public string $url,
        public ?string $route = null,
    ) {}
}
