<?php

// FR-019: the two-method state contract, selected by one config value.
// FR-020: under the browser-local default, no route exists at all.

use Illuminate\Support\Facades\Route;
use Rolland\FilamentTours\Contracts\TourState;
use Rolland\FilamentTours\State\LocalStorageState;

it('registers the package config under the filament-tours key', function () {
    expect(config('filament-tours.state'))->toBe('local');
});

it('is the browser, not the server, that answers under the local driver', function () {
    $state = new LocalStorageState;

    // Not a stub: under this driver localStorage holds the answer, so a server
    // claiming to know would be lying. Answering false always is the contract.
    expect($state->hasSeen('anything'))->toBeFalse()
        ->and($state->hasSeen('seen-everywhere-else'))->toBeFalse();
});

it('records nothing server-side under the local driver', function () {
    $state = new LocalStorageState;

    $state->markSeen('a-tour');

    expect($state->hasSeen('a-tour'))->toBeFalse();
});

it('is a tour state driver', function () {
    expect(new LocalStorageState)->toBeInstanceOf(TourState::class);
});

/*
 * FR-020. The absence assertion: under the browser-local default there is
 * nothing to write server-side, so there must be no endpoint at all — not a
 * guarded one, not a 404ing one. No route is the smallest attack surface.
 */
it('registers no route at all under the local driver', function () {
    $tourRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route): bool => str_contains((string) $route->uri(), 'filament-tours'));

    expect($tourRoutes)->toBeEmpty();
});

it('refuses a state driver that is neither local nor a real class', function () {
    config()->set('filament-tours.state', 'App\\Nope\\Missing');

    expect(fn () => app(TourState::class))->toThrow(InvalidArgumentException::class, 'Missing');
});

it('refuses a class that does not implement the contract', function () {
    config()->set('filament-tours.state', stdClass::class);

    expect(fn () => app(TourState::class))->toThrow(InvalidArgumentException::class, 'TourState');
});
