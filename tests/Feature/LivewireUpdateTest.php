<?php

declare(strict_types=1);

use Livewire\Mechanisms\HandleComponents\CorruptComponentPayloadException;
use Syriable\Packages\LivewireNavigation\Navigation;

beforeEach(fn () => defineRoutes());

it('reports the page the component lives on instead of the livewire endpoint', function (): void {
    $this->get('/a');
    $snapshot = snapshots($this->get('/b', ['Referer' => 'http://localhost/a']))[0];

    expect(seenInUpdate($snapshot))
        ->toBe(state('http://localhost/b', 'b', 'http://localhost/a', 'a'));
});

it('does not record livewire updates as page visits', function (): void {
    $this->get('/a');
    $snapshot = snapshots($this->get('/b', ['Referer' => 'http://localhost/a']))[0];

    seenInUpdate($snapshot);
    seenInUpdate($snapshot);

    expect(seenOnPage($this->get('/c')))
        ->toBe(state('http://localhost/c', 'c', 'http://localhost/b', 'b'));
});

it('keeps each browser tab on its own page', function (): void {
    $this->get('/a');
    $firstTab = snapshots($this->get('/b', ['Referer' => 'http://localhost/a']))[0];

    $this->get('/users/9');
    $secondTab = snapshots($this->get('/c', ['Referer' => 'http://localhost/users/9']))[0];

    expect(seenInUpdate($firstTab))
        ->toBe(state('http://localhost/b', 'b', 'http://localhost/a', 'a'))
        ->and(seenInUpdate($secondTab))
        ->toBe(state('http://localhost/c', 'c', 'http://localhost/users/9', 'users.show'));
});

it('keeps the page after repeated updates', function (): void {
    $snapshot = snapshots($this->get('/b', ['Referer' => 'http://localhost/a']))[0];

    $response = livewireCall($snapshot, 'capture')->assertOk();
    $this->get('/c');

    expect(seenInUpdate($response->json('components.0.snapshot')))
        ->toBe(state('http://localhost/b', 'b', 'http://localhost/a', 'a'));
});

it('picks up query string changes the browser made after the page loaded', function (): void {
    $snapshot = snapshots($this->get('/users/5?tab=posts'))[0];

    expect(seenInUpdate($snapshot, ['Referer' => 'http://localhost/users/5?tab=likes&page=2']))
        ->toBe(state('http://localhost/users/5?page=2&tab=likes', 'users.show', null, null));
});

it('ignores a referer pointing to another page or another origin', function (string $referer): void {
    $snapshot = snapshots($this->get('/b'))[0];

    expect(seenInUpdate($snapshot, ['Referer' => $referer]))
        ->toBe(state('http://localhost/b', 'b', null, null));
})->with([
    'another page' => 'http://localhost/c?x=1',
    'another origin' => 'http://evil.test/b?x=1',
    'origin with the same prefix' => 'http://localhost.evil.test/b?x=1',
]);

it('resolves the page for nested components', function (): void {
    $this->get('/a');
    [$parent, $child] = snapshots($this->get('/nested', ['Referer' => 'http://localhost/a']));

    expect(seen($child))
        ->toBe(state('http://localhost/nested', 'nested', 'http://localhost/a', 'a'))
        ->and(seenInUpdate($child))
        ->toBe(state('http://localhost/nested', 'nested', 'http://localhost/a', 'a'))
        ->and(json_decode($parent, true)['memo']['navigation'])
        ->toBe(['url' => 'http://localhost/nested', 'route' => 'nested', 'previous_url' => 'http://localhost/a', 'previous_route' => 'a']);
});

it('passes the page on to components first rendered during an update', function (): void {
    $this->get('/a');
    $parent = snapshots($this->get('/nested-hidden', ['Referer' => 'http://localhost/a']))[0];
    $this->get('/c');

    $html = livewireCall($parent, 'reveal')->assertOk()->json('components.0.effects.html');
    preg_match('/wire:snapshot="([^"]*)"/', $html, $matches);
    $child = html_entity_decode($matches[1], ENT_QUOTES);

    expect(seen($child))
        ->toBe(state('http://localhost/nested-hidden', 'nested-hidden', 'http://localhost/a', 'a'))
        ->and(seenInUpdate($child))
        ->toBe(state('http://localhost/nested-hidden', 'nested-hidden', 'http://localhost/a', 'a'));
});

it('rejects snapshots whose navigation memo was tampered with', function (): void {
    $snapshot = json_decode(snapshots($this->get('/b'))[0], true);
    $snapshot['memo']['navigation']['url'] = 'http://evil.test';

    $this->withoutExceptionHandling();

    livewireCall((string) json_encode($snapshot), 'capture');
})->throws(CorruptComponentPayloadException::class);

it('falls back to the latest recorded page for snapshots without navigation memo', function (): void {
    $this->get('/a');
    $this->get('/b', ['Referer' => 'http://localhost/a']);

    $snapshot = json_decode(snapshots($this->get('/c'))[0], true);
    unset($snapshot['memo']['navigation']);

    app(Navigation::class)->rememberSnapshot($snapshot);

    expect(app(Navigation::class)->currentUrl())->toBe('http://localhost/c')
        ->and(app(Navigation::class)->previousUrl())->toBe('http://localhost/b');
});
