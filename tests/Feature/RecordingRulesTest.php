<?php

declare(strict_types=1);

use Syriable\Packages\LivewireNavigation\Navigation;

beforeEach(function (): void {
    defineRoutes();

    $this->get('/a');
});

it('does not record requests that do not display a page', function (string $method, string $uri, array $headers): void {
    $this->call($method, $uri, server: $this->transformHeadersToServerVars($headers));

    expect(seenOnPage($this->get('/c')))
        ->toBe(state('http://localhost/c', 'c', 'http://localhost/a', 'a'));
})->with([
    'redirect' => ['GET', '/redirect', []],
    'json' => ['GET', '/json', ['Accept' => 'application/json']],
    'plain text' => ['GET', '/text', []],
    'forbidden page' => ['GET', '/forbidden', []],
    'server error page' => ['GET', '/broken', []],
    'missing route' => ['GET', '/does-not-exist', []],
    'download' => ['GET', '/download', []],
    'ajax' => ['GET', '/b', ['X-Requested-With' => 'XMLHttpRequest']],
    'prefetch' => ['GET', '/b', ['Sec-Purpose' => 'prefetch']],
    'head' => ['HEAD', '/b', []],
    'post' => ['POST', '/action', []],
    'put' => ['PUT', '/action', []],
    'patch' => ['PATCH', '/action', []],
    'delete' => ['DELETE', '/action', []],
]);

it('does not record precognitive requests', function (): void {
    // Asserted on the session directly: Laravel 12.0 keeps the precognitive
    // dispatchers bound in the container for the following test requests.
    $this->get('/precognitive', ['Precognition' => 'true']);

    expect(session(Navigation::SESSION_KEY)['url'])->toBe('http://localhost/a');
});

it('records the destination of a redirect but not the redirecting url', function (): void {
    $this->get('/redirect', ['Referer' => 'http://localhost/a'])->assertRedirect('/b');

    expect(seenOnPage($this->get('/b', ['Referer' => 'http://localhost/a'])))
        ->toBe(state('http://localhost/b', 'b', 'http://localhost/a', 'a'))
        ->and(seenOnPage($this->get('/c')))
        ->toBe(state('http://localhost/c', 'c', 'http://localhost/b', 'b'));
});

it('reports the displayed page to form submissions made from it', function (string $method): void {
    $this->get('/b', ['Referer' => 'http://localhost/a']);

    $this->call($method, '/action')
        ->assertExactJson(state('http://localhost/b', 'b', 'http://localhost/a', 'a'));
})->with(['POST', 'PUT', 'PATCH', 'DELETE']);

it('keeps the previous page when a failed validation redirects back', function (): void {
    $this->get('/b', ['Referer' => 'http://localhost/a']);

    $this->post('/form', [], ['Referer' => 'http://localhost/b'])->assertRedirect('http://localhost/b');

    expect(seenOnPage($this->get('/b', ['Referer' => 'http://localhost/b'])))
        ->toBe(state('http://localhost/b', 'b', 'http://localhost/a', 'a'));
});

it('follows a successful form submission to the page it redirects to', function (): void {
    $this->get('/b', ['Referer' => 'http://localhost/a']);

    $this->post('/form', ['name' => 'Taylor'], ['Referer' => 'http://localhost/b'])->assertRedirect('/c');

    expect(seenOnPage($this->get('/c', ['Referer' => 'http://localhost/b'])))
        ->toBe(state('http://localhost/c', 'c', 'http://localhost/b', 'b'));
});
