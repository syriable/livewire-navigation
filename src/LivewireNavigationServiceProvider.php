<?php

declare(strict_types=1);

namespace Syriable\Packages\LivewireNavigation;

use Illuminate\Contracts\Http\Kernel as KernelContract;
use Illuminate\Foundation\Http\Kernel;
use Illuminate\Support\ServiceProvider;
use Livewire\LivewireManager;
use Livewire\Mechanisms\HandleComponents\ComponentContext;
use Syriable\Packages\LivewireNavigation\Http\Middleware\TrackNavigation;

final class LivewireNavigationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(Navigation::class);
    }

    public function boot(): void
    {
        $this->callAfterResolving(KernelContract::class, function (KernelContract $kernel): void {
            if ($kernel instanceof Kernel && array_key_exists('web', $kernel->getMiddlewareGroups())) {
                $kernel->appendMiddlewareToGroup('web', TrackNavigation::class);
            }
        });

        // The listeners resolve the service through the global container so they
        // always act on the container of the request currently being handled.
        $this->callAfterResolving(LivewireManager::class, function (LivewireManager $livewire): void {
            $livewire->listen('snapshot-verified', function (array $snapshot): void {
                app(Navigation::class)->rememberSnapshot($snapshot);
            });

            $livewire->listen('dehydrate', function (mixed $component, ComponentContext $context): void {
                if ($visit = app(Navigation::class)->visitForMemo()) {
                    $context->addMemo(Navigation::MEMO_KEY, $visit->toArray());
                }
            });
        });
    }
}
