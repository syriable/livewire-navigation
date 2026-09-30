<?php

declare(strict_types=1);

namespace Syriable\Packages\LivewireNavigation\Tests\Fixtures;

use Livewire\Component;
use Syriable\Packages\LivewireNavigation\Facades\Navigation;

/**
 * Captures what the package resolves, both while mounting and during updates.
 */
class Probe extends Component
{
    public string $seen = '';

    public function mount(): void
    {
        $this->capture();
    }

    public function capture(): void
    {
        $this->seen = (string) json_encode([
            'current_url' => Navigation::currentUrl(),
            'current_route' => Navigation::currentRoute(),
            'previous_url' => Navigation::previousUrl(),
            'previous_route' => Navigation::previousRoute(),
        ]);
    }

    public function render(): string
    {
        return '<div>probe</div>';
    }
}
