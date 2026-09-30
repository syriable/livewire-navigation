<?php

declare(strict_types=1);

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Route;
use RalphJSmit\Livewire\Urls\Facades\Url;
use RalphJSmit\Livewire\Urls\Middleware\LivewireUrlsMiddleware;
use Syriable\Packages\LivewireNavigation\Facades\Navigation;

/*
 * Runs ralphjsmit/livewire-urls next to this package on the same requests.
 * Equivalent behaviour is asserted to be identical; every intentional
 * difference is asserted explicitly and explained.
 */

beforeEach(function (): void {
    defineRoutes();

    app(Kernel::class)->appendMiddlewareToGroup('web', LivewireUrlsMiddleware::class);

    Route::middleware('web')->post('/compare', fn (): array => [
        'original' => state(Url::current(), Url::currentRoute(), Url::previous(), Url::previousRoute()),
        'new' => state(
            Navigation::currentUrl(),
            Navigation::currentRoute(),
            Navigation::previousUrl(),
            Navigation::previousRoute(),
        ),
    ]);
});

/**
 * @return array{original: array<string, ?string>, new: array<string, ?string>}
 */
function compare(): array
{
    return test()->post('/compare')->assertOk()->json();
}

it('matches the original package for consecutive page visits', function (): void {
    $this->get('/a');
    $this->get('/b');

    expect(compare())->toBe([
        'original' => $expected = state('http://localhost/b', 'b', 'http://localhost/a', 'a'),
        'new' => $expected,
    ]);
});

it('matches the original package for unnamed routes', function (): void {
    $this->get('/unnamed');
    $this->get('/a');

    expect(compare())->toBe([
        'original' => $expected = state('http://localhost/a', 'a', 'http://localhost/unnamed', null),
        'new' => $expected,
    ]);
});

it('matches the original package by ignoring non GET requests', function (): void {
    $this->get('/a');
    $this->post('/action');
    $this->put('/action');
    $this->patch('/action');
    $this->delete('/action');

    expect(compare())->toBe([
        'original' => $expected = state('http://localhost/a', 'a', null, null),
        'new' => $expected,
    ]);
});

it('matches the original package by ignoring livewire updates', function (): void {
    $this->get('/a');
    $snapshot = snapshots($this->get('/b'))[0];

    livewireCall($snapshot, 'capture')->assertOk();

    expect(compare())->toBe([
        'original' => $expected = state('http://localhost/b', 'b', 'http://localhost/a', 'a'),
        'new' => $expected,
    ]);
});

it('differs by keeping the previous page when a page is reloaded', function (): void {
    // The original shifts the reloaded page into "previous", which is why it
    // needed lastRecorded(). A reload does not navigate anywhere, so it is ignored.
    $this->get('/a');
    $this->get('/b');
    $this->get('/b');

    expect(compare())->toBe([
        'original' => state('http://localhost/b', 'b', 'http://localhost/b', 'b'),
        'new' => state('http://localhost/b', 'b', 'http://localhost/a', 'a'),
    ]);
});

it('differs by not recording redirecting urls', function (): void {
    // The browser never displays a redirect response, so it cannot be the page
    // the user came from.
    $this->get('/a');
    $this->get('/redirect');
    $this->get('/b');

    expect(compare())->toBe([
        'original' => state('http://localhost/b', 'b', 'http://localhost/redirect', 'redirect'),
        'new' => state('http://localhost/b', 'b', 'http://localhost/a', 'a'),
    ]);
});

it('differs by not recording responses that are not html pages', function (): void {
    // JSON endpoints fetched by scripts are not pages the user visited.
    $this->get('/a');
    $this->get('/json');

    expect(compare())->toBe([
        'original' => state('http://localhost/json', 'json', 'http://localhost/a', 'a'),
        'new' => state('http://localhost/a', 'a', null, null),
    ]);
});

it('differs by resolving livewire updates per browser tab', function (): void {
    // The original only knows the latest page of the whole session, so a
    // component in an older tab is told it lives on the page of the newest tab.
    $this->get('/a');
    $firstTab = snapshots($this->get('/b', ['Referer' => 'http://localhost/a']))[0];
    $this->get('/c');

    livewireCall($firstTab, 'capture')->assertOk();

    expect(Url::current())->toBe('http://localhost/c')
        ->and(seenInUpdate($firstTab))->toBe(state('http://localhost/b', 'b', 'http://localhost/a', 'a'));
});
