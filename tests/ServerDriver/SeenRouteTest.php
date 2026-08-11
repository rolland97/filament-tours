<?php

use Illuminate\Support\Facades\Route;
use Rolland\FilamentTours\Tests\Support\FakeServerState;

function seenUrl(string $tour): string
{
    return route('filament.testing.filament-tours.seen', ['tour' => $tour]);
}

it('registers exactly one route under a host driver', function () {
    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route): bool => str_contains((string) $route->uri(), 'filament-tours'));

    expect($routes)->toHaveCount(1);

    $route = $routes->first();

    expect($route->methods())->toContain('POST')
        ->and($route->uri())->toContain('filament-tours/{tour}/seen')
        // Filament prefixes with 'filament.' and the panel id.
        ->and($route->getName())->toBe('filament.testing.filament-tours.seen');
});

it('rejects an unauthenticated caller', function () {
    // Asserted as behaviour, not as a middleware class name: what matters is
    // that an anonymous request cannot write, however Filament spells that.
    auth()->logout();

    $response = $this->post(seenUrl('page-a-tour'));

    expect($response->status())->not->toBe(204)
        ->and(FakeServerState::$marked)->toBe([]);
});

it('records a known tour through the host driver', function () {
    $this->post(seenUrl('page-a-tour'))
        ->assertNoContent();

    expect(FakeServerState::$marked)->toBe(['page-a-tour']);
});

it('refuses an unregistered tour id without touching the driver', function () {
    // The segment is not a free-form key a caller can write anything into.
    $this->post(seenUrl('no-such-tour'))
        ->assertNotFound();

    expect(FakeServerState::$marked)->toBe([]);
});

it('never exposes a way to read seen state', function () {
    $this->get(seenUrl('page-a-tour'))
        ->assertMethodNotAllowed();
});
