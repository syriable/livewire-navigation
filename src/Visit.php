<?php

declare(strict_types=1);

namespace Syriable\Packages\LivewireNavigation;

/**
 * The page being displayed together with the page displayed before it.
 *
 * This is the unit that is written to the session after a page visit and
 * embedded in the memo of every Livewire component rendered on that page.
 *
 * @internal
 */
final readonly class Visit
{
    public function __construct(
        public Page $current,
        public ?Page $previous = null,
    ) {}

    /**
     * Rebuild a visit from untrusted storage, returning null for anything malformed.
     */
    public static function fromArray(mixed $data): ?self
    {
        if (! is_array($data) || ! is_string($data['url'] ?? null)) {
            return null;
        }

        $previous = is_string($data['previous_url'] ?? null)
            ? new Page($data['previous_url'], self::routeName($data['previous_route'] ?? null))
            : null;

        return new self(new Page($data['url'], self::routeName($data['route'] ?? null)), $previous);
    }

    /**
     * @return array{url: string, route: ?string, previous_url: ?string, previous_route: ?string}
     */
    public function toArray(): array
    {
        return [
            'url' => $this->current->url,
            'route' => $this->current->route,
            'previous_url' => $this->previous?->url,
            'previous_route' => $this->previous?->route,
        ];
    }

    private static function routeName(mixed $route): ?string
    {
        return is_string($route) ? $route : null;
    }
}
