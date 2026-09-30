<?php

declare(strict_types=1);

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Session\Middleware\StartSession;
use Syriable\Packages\LivewireNavigation\Facades\Navigation as NavigationFacade;
use Syriable\Packages\LivewireNavigation\Http\Middleware\TrackNavigation;
use Syriable\Packages\LivewireNavigation\Navigation;

beforeEach(fn () => defineRoutes());

it('registers the middleware in the web group once', function (): void {
    app()->make(Kernel::class);

    $web = app(Kernel::class)->getMiddlewareGroups()['web'];

    expect(array_count_values($web)[TrackNavigation::class] ?? 0)->toBe(1)
        ->and(array_search(TrackNavigation::class, $web))
        ->toBeGreaterThan(array_search(StartSession::class, $web));
});

it('resolves the service through the facade and the container', function (): void {
    expect(NavigationFacade::getFacadeRoot())->toBe(app(Navigation::class))
        ->and(app(Navigation::class))->toBeInstanceOf(Navigation::class);
});

it('works on routes without a session', function (): void {
    expect(seenOnPage($this->get('/stateless', ['Referer' => 'http://localhost/a'])))
        ->toBe(state('http://localhost/stateless', 'stateless', 'http://localhost/a', 'a'));
});

it('ignores malformed session data', function (mixed $stored): void {
    $this->withSession([Navigation::SESSION_KEY => $stored]);

    expect(seenOnPage($this->get('/b')))->toBe(state('http://localhost/b', 'b', null, null));
})->with([
    'string' => 'http://localhost/a',
    'missing url' => [['route' => 'a']],
    'non string url' => [['url' => 42]],
]);

it('ignores non string route names in stored data', function (): void {
    $this->withSession([Navigation::SESSION_KEY => ['url' => 'http://localhost/a', 'route' => ['a'], 'previous_url' => 'http://localhost', 'previous_route' => 1]]);

    $this->post('/action')->assertExactJson(state('http://localhost/a', null, 'http://localhost', null));
});
