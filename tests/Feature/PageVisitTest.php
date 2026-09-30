<?php

declare(strict_types=1);
use Syriable\Packages\LivewireNavigation\Navigation;

beforeEach(fn () => defineRoutes());

it('has no previous page on the first visit', function (): void {
    expect(seenOnPage($this->get('/a')))
        ->toBe(state('http://localhost/a', 'a', null, null));
});

it('uses the latest recorded page as previous page when there is no referer', function (): void {
    $this->get('/a');

    expect(seenOnPage($this->get('/b')))
        ->toBe(state('http://localhost/b', 'b', 'http://localhost/a', 'a'));
});

it('uses the referer of the browser tab as previous page', function (): void {
    $this->get('/a');
    $this->get('/c');

    expect(seenOnPage($this->get('/b', ['Referer' => 'http://localhost/a'])))
        ->toBe(state('http://localhost/b', 'b', 'http://localhost/a', 'a'));
});

it('resolves the route name of a referer that was never recorded', function (): void {
    expect(seenOnPage($this->get('/b', ['Referer' => 'http://localhost/users/7'])))
        ->toBe(state('http://localhost/b', 'b', 'http://localhost/users/7', 'users.show'));
});

it('keeps the previous page when the same page is loaded again', function (): void {
    $this->get('/a');
    $this->get('/b', ['Referer' => 'http://localhost/a']);

    expect(seenOnPage($this->get('/b')))
        ->toBe(state('http://localhost/b', 'b', 'http://localhost/a', 'a'))
        ->and(seenOnPage($this->get('/b', ['Referer' => 'http://localhost/b'])))
        ->toBe(state('http://localhost/b', 'b', 'http://localhost/a', 'a'));
});

it('moves forward through a chain of pages', function (): void {
    $this->get('/a');
    $this->get('/b', ['Referer' => 'http://localhost/a']);

    expect(seenOnPage($this->get('/c', ['Referer' => 'http://localhost/b'])))
        ->toBe(state('http://localhost/c', 'c', 'http://localhost/b', 'b'))
        ->and(seenOnPage($this->get('/a', ['Referer' => 'http://localhost/c'])))
        ->toBe(state('http://localhost/a', 'a', 'http://localhost/c', 'c'));
});

it('treats wire:navigate requests as page visits', function (): void {
    $this->get('/a');

    expect(seenOnPage($this->get('/b', ['X-Livewire-Navigate' => '1', 'Referer' => 'http://localhost/a'])))
        ->toBe(state('http://localhost/b', 'b', 'http://localhost/a', 'a'))
        ->and(seenOnPage($this->get('/c')))
        ->toBe(state('http://localhost/c', 'c', 'http://localhost/b', 'b'));
});

it('reports unnamed routes with a null route name', function (): void {
    $this->get('/unnamed');

    expect(seenOnPage($this->get('/a')))
        ->toBe(state('http://localhost/a', 'a', 'http://localhost/unnamed', null));
});

it('includes route parameters and the query string in the url', function (): void {
    $this->get('/users/5?tab=posts');

    expect(seenOnPage($this->get('/a')))
        ->toBe(state('http://localhost/a', 'a', 'http://localhost/users/5?tab=posts', 'users.show'));
});

it('exposes the same state to controllers and components of a page', function (): void {
    $this->get('/a');

    $this->get('/json', ['Referer' => 'http://localhost/a'])
        ->assertExactJson(state('http://localhost/json', 'json', 'http://localhost/a', 'a'));
});

it('returns the fallbacks when nothing is known', function (): void {
    $navigation = app(Navigation::class);

    expect($navigation->currentUrl())->toBeNull()
        ->and($navigation->currentRoute())->toBeNull()
        ->and($navigation->previousUrl())->toBeNull()
        ->and($navigation->previousRoute())->toBeNull()
        ->and($navigation->currentUrl('/fallback'))->toBe('/fallback')
        ->and($navigation->currentRoute('fallback'))->toBe('fallback')
        ->and($navigation->previousUrl('/fallback'))->toBe('/fallback')
        ->and($navigation->previousRoute('fallback'))->toBe('fallback');
});
