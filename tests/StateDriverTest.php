<?php

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
