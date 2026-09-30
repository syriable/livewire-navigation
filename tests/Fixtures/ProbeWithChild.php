<?php

declare(strict_types=1);

namespace Syriable\Packages\LivewireNavigation\Tests\Fixtures;

use Livewire\Component;

/**
 * Renders a nested probe, optionally only after an update has revealed it.
 */
class ProbeWithChild extends Component
{
    public bool $showChild = true;

    public function reveal(): void
    {
        $this->showChild = true;
    }

    public function render(): string
    {
        return <<<'BLADE'
            <div>
                @if ($showChild)
                    <livewire:probe />
                @endif
            </div>
            BLADE;
    }
}
