<?php

// FR-001: the fluent definition surface, frozen for v1.
// FR-003: selectors are raw CSS — validated for emptiness, never for matching.

use Rolland\FilamentTours\Step;

it('keeps the selector it was made with', function () {
    expect(Step::make('[data-tour="thing"]')->getSelector())->toBe('[data-tour="thing"]');
});

it('resolves copy when it is read, not when it is declared', function () {
    /*
     * 🚨 The reason this exists. A Filament panel is configured BEFORE any
     * request middleware runs, so a step declared with __('...') resolves in the
     * application's default locale and stays there — every reader sees that one
     * language, whatever their preference. A closure is resolved when the
     * payload is built, by which point the request's locale is set.
     *
     * Consumers were writing a middleware to rebuild their tours for this. They
     * should not have to.
     */
    app()->setLocale('en');

    $step = Step::make('#a')
        ->title(fn (): string => 'Title in ' . app()->getLocale())
        ->body(fn (): string => 'Body in ' . app()->getLocale());

    app()->setLocale('ms');

    expect($step->getTitle())->toBe('Title in ms')
        ->and($step->getBody())->toBe('Body in ms');
});

it('still takes plain strings, for wording that never varies', function () {
    $step = Step::make('#a')->title('Fixed')->body('Also fixed');

    expect($step->getTitle())->toBe('Fixed')
        ->and($step->getBody())->toBe('Also fixed');
});

it('defaults every optional field to null', function () {
    $step = Step::make('#a');

    expect($step->getTitle())->toBeNull()
        ->and($step->getBody())->toBeNull()
        ->and($step->getSide())->toBeNull()
        ->and($step->getAlign())->toBeNull();
});

it('carries the copy and placement it is given', function () {
    $step = Step::make('#a')
        ->title('Heading')
        ->body('Words.')
        ->side('left')
        ->align('start');

    expect($step->getTitle())->toBe('Heading')
        ->and($step->getBody())->toBe('Words.')
        ->and($step->getSide())->toBe('left')
        ->and($step->getAlign())->toBe('start');
});

it('rejects a selector that is empty or only whitespace', function (string $selector) {
    Step::make($selector);
})->with(['', '   '])->throws(InvalidArgumentException::class);

it('rejects a side it cannot pass to the tour engine', function () {
    Step::make('#a')->side('sideways');
})->throws(InvalidArgumentException::class, 'sideways');

it('rejects an alignment it cannot pass to the tour engine', function () {
    Step::make('#a')->align('middle');
})->throws(InvalidArgumentException::class, 'middle');

it('accepts every side and alignment the contract allows', function () {
    foreach (['top', 'right', 'bottom', 'left'] as $side) {
        expect(Step::make('#a')->side($side)->getSide())->toBe($side);
    }

    foreach (['start', 'center', 'end'] as $align) {
        expect(Step::make('#a')->align($align)->getAlign())->toBe($align);
    }
});
