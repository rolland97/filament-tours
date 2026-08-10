<?php

use Rolland\FilamentTours\Step;

it('keeps the selector it was made with', function () {
    expect(Step::make('[data-tour="thing"]')->getSelector())->toBe('[data-tour="thing"]');
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
