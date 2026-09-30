<?php

declare(strict_types=1);
use Illuminate\Support\Facades\URL;

beforeEach(fn () => defineRoutes());

it('normalizes referers the same way laravel normalizes request urls', function (string $referer, string $expected): void {
    expect(seenOnPage($this->get('/b', ['Referer' => $referer]))['previous_url'])->toBe($expected);
})->with([
    'trailing slash' => ['http://localhost/a/', 'http://localhost/a'],
    'fragment' => ['http://localhost/a#section', 'http://localhost/a'],
    'query order' => ['http://localhost/a?b=2&a=1', 'http://localhost/a?a=1&b=2'],
    'encoded parameters' => ['http://localhost/users/j%C3%B6rg?q=a%20b', 'http://localhost/users/j%C3%B6rg?q=a%20b'],
    'root' => ['http://localhost/', 'http://localhost'],
]);

it('ignores referers from other origins', function (string $referer): void {
    $this->get('/a');

    expect(seenOnPage($this->get('/b', ['Referer' => $referer]))['previous_url'])->toBe('http://localhost/a');
})->with([
    'other host' => 'https://example.com/a',
    'host with the same prefix' => 'http://localhost.example.com/a',
    'other scheme' => 'https://localhost/a',
    'other port' => 'http://localhost:8080/a',
    'empty' => '',
]);

it('treats the same page with a different query string as another page', function (): void {
    $this->get('/users/5?page=1');

    expect(seenOnPage($this->get('/users/5?page=2')))
        ->toBe(state('http://localhost/users/5?page=2', 'users.show', 'http://localhost/users/5?page=1', 'users.show'));
});

it('keeps the signature of signed urls', function (): void {
    $signed = URL::signedRoute('users.show', ['user' => 5]);

    $this->get($signed);

    expect(seenOnPage($this->get('/a'))['previous_url'])->toBe($signed);
});

it('uses the scheme, host and port of the request', function (): void {
    $this->get('https://app.test:8443/a');

    expect(seenOnPage($this->get('https://app.test:8443/b', ['Referer' => 'https://app.test:8443/a'])))
        ->toBe(state('https://app.test:8443/b', 'b', 'https://app.test:8443/a', 'a'));
});

it('leaves the route name empty for a referer no route matches', function (): void {
    expect(seenOnPage($this->get('/b', ['Referer' => 'http://localhost/nowhere'])))
        ->toBe(state('http://localhost/b', 'b', 'http://localhost/nowhere', null));
});
