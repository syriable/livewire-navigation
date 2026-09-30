<?php

declare(strict_types=1);

namespace Syriable\Packages\LivewireNavigation\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string|null currentUrl(?string $fallback = null)
 * @method static string|null currentRoute(?string $fallback = null)
 * @method static string|null previousUrl(?string $fallback = null)
 * @method static string|null previousRoute(?string $fallback = null)
 *
 * @see \Syriable\Packages\LivewireNavigation\Navigation
 */
final class Navigation extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Syriable\Packages\LivewireNavigation\Navigation::class;
    }
}
