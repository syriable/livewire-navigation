<?php

declare(strict_types=1);

use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Livewire\Mechanisms\HandleRequests\HandleRequests;
use Syriable\Packages\LivewireNavigation\Facades\Navigation;
use Syriable\Packages\LivewireNavigation\Tests\TestCase;

uses(TestCase::class)->in('Feature');

/**
 * Register the routes shared by the feature tests, all inside the "web" group.
 */
function defineRoutes(): void
{
    $probe = fn (): string => Blade::render('<livewire:probe />');
    $state = fn (): array => [
        'current_url' => Navigation::currentUrl(),
        'current_route' => Navigation::currentRoute(),
        'previous_url' => Navigation::previousUrl(),
        'previous_route' => Navigation::previousRoute(),
    ];

    Route::middleware('web')->group(function () use ($probe, $state): void {
        Route::get('/', $probe)->name('home');
        Route::get('/a', $probe)->name('a');
        Route::get('/b', $probe)->name('b');
        Route::get('/c', $probe)->name('c');
        Route::get('/users/{user}', $probe)->name('users.show');
        Route::get('/unnamed', $probe);
        Route::get('/nested', fn (): string => Blade::render('<livewire:probe-with-child />'))->name('nested');
        Route::get('/nested-hidden', fn (): string => Blade::render('<livewire:probe-with-child :show-child="false" />'))->name('nested-hidden');
        Route::get('/redirect', fn (): Redirector|\Illuminate\Http\RedirectResponse => redirect('/b'))->name('redirect');
        Route::get('/json', $state)->name('json');
        Route::get('/text', fn (): ResponseFactory|\Illuminate\Http\Response => response('plain', 200, ['Content-Type' => 'text/plain']))->name('text');
        Route::get('/forbidden', fn () => abort(403))->name('forbidden');
        Route::get('/broken', fn () => throw new RuntimeException('Broken page.'))->name('broken');
        Route::get('/download', fn () => response()->streamDownload(fn (): int => print ('file'), 'file.txt'))->name('download');
        Route::get('/precognitive', $probe)->middleware(HandlePrecognitiveRequests::class)->name('precognitive');
        Route::post('/form', function (Request $request): Redirector|RedirectResponse {
            $request->validate(['name' => 'required']);

            return redirect('/c');
        })->name('form');
        Route::match(['POST', 'PUT', 'PATCH', 'DELETE'], '/action', $state)->name('action');
    });

    Route::get('/stateless', $probe)->name('stateless');
}

/**
 * Livewire snapshots rendered in a response, in document order.
 *
 * @return list<string>
 */
function snapshots(TestResponse $response): array
{
    preg_match_all('/wire:snapshot="([^"]*)"/', (string) $response->getContent(), $matches);

    return array_map(fn (string $snapshot): string => html_entity_decode($snapshot, ENT_QUOTES), $matches[1]);
}

/**
 * The navigation state a probe component captured, read from its snapshot.
 *
 * @return array<string, ?string>
 */
function seen(string $snapshot): array
{
    return json_decode(json_decode($snapshot, true)['data']['seen'], true);
}

/**
 * The navigation state captured by the first probe while the page rendered.
 *
 * @return array<string, ?string>
 */
function seenOnPage(TestResponse $response): array
{
    return seen(snapshots($response)[0]);
}

/**
 * Send a Livewire update calling one method on the component, like the browser does.
 *
 * @param  array<string, string>  $headers
 */
function livewireCall(string $snapshot, string $method, array $headers = []): TestResponse
{
    $response = test()->withHeaders(['X-Livewire' => '1', ...$headers])->postJson(app(HandleRequests::class)->getUpdateUri(), [
        'components' => [
            ['snapshot' => $snapshot, 'updates' => [], 'calls' => [['method' => $method, 'params' => [], 'metadata' => []]]],
        ],
    ]);

    test()->flushHeaders();

    return $response;
}

/**
 * The navigation state a probe captured during a Livewire update.
 *
 * @param  array<string, string>  $headers
 * @return array<string, ?string>
 */
function seenInUpdate(string $snapshot, array $headers = []): array
{
    $response = livewireCall($snapshot, 'capture', $headers)->assertOk();

    return seen($response->json('components.0.snapshot'));
}

/**
 * @return array{current_url: ?string, current_route: ?string, previous_url: ?string, previous_route: ?string}
 */
function state(?string $currentUrl, ?string $currentRoute, ?string $previousUrl, ?string $previousRoute): array
{
    return [
        'current_url' => $currentUrl,
        'current_route' => $currentRoute,
        'previous_url' => $previousUrl,
        'previous_route' => $previousRoute,
    ];
}
