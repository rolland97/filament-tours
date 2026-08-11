<?php

use Rolland\FilamentTours\Actions\StartTourAction;

it('dispatches the documented start event for its tour', function () {
    $handler = StartTourAction::make('itrf-create')->getAlpineClickHandler();

    expect($handler)->toContain('filament-tours:start')
        ->and($handler)->toContain('itrf-create');
});

it('runs entirely in the browser, with no server round trip', function () {
    // Replay is a client concern: the payload is already on the page, so
    // asking the server again would be a round trip for data we hold.
    $action = StartTourAction::make('itrf-create');

    expect($action->getAlpineClickHandler())->not->toBeNull()
        ->and($action->getActionFunction())->toBeNull();
});

it('takes the tour id as its name', function () {
    expect(StartTourAction::make('itrf-create')->getName())->toBe('itrf-create');
});

it('escapes a tour id containing a quote', function () {
    // Defence in depth. Tour ids are developer-authored, but this string is
    // interpolated into an Alpine expression, so a stray quote must not be
    // able to close it and append its own JavaScript.
    $handler = StartTourAction::make("evil'); alert(1); //")->getAlpineClickHandler();

    expect($handler)->not->toContain("'); alert(1)");
});
