<?php

declare(strict_types=1);

namespace Syriable\Packages\LivewireNavigation\Tests;

use Livewire\Livewire;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Override;
use Syriable\Packages\LivewireNavigation\LivewireNavigationServiceProvider;
use Syriable\Packages\LivewireNavigation\Tests\Fixtures\Probe;
use Syriable\Packages\LivewireNavigation\Tests\Fixtures\ProbeWithChild;

abstract class TestCase extends Orchestra
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        Livewire::component('probe', Probe::class);
        Livewire::component('probe-with-child', ProbeWithChild::class);
    }

    #[Override]
    protected function getPackageProviders($app): array
    {
        return [
            LivewireServiceProvider::class,
            LivewireNavigationServiceProvider::class,
        ];
    }

    #[Override]
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
        $app['config']->set('app.url', 'http://localhost');
        $app['config']->set('session.driver', 'array');
    }
}
