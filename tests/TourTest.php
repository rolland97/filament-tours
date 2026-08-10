<?php

use Rolland\FilamentTours\Step;
use Rolland\FilamentTours\Tests\Panel\Pages\PageA;
use Rolland\FilamentTours\Tour;

it('keeps the id it was made with', function () {
    expect(Tour::make('itrf-create')->getId())->toBe('itrf-create');
});

it('does not run once unless asked to', function () {
    expect(Tour::make('a')->isOnce())->toBeFalse()
        ->and(Tour::make('a')->once()->isOnce())->toBeTrue()
        ->and(Tour::make('a')->once(false)->isOnce())->toBeFalse();
});

it('has no page and no predicate until given one', function () {
    $tour = Tour::make('a');

    expect($tour->getPageClass())->toBeNull()
        ->and($tour->getPredicate())->toBeNull();
});

it('carries the page class it targets', function () {
    expect(Tour::make('a')->for(PageA::class)->getPageClass())->toBe(PageA::class);
});

it('keeps its steps in the order they were declared', function () {
    $tour = Tour::make('a')->steps([
        Step::make('#first'),
        Step::make('#second'),
        Step::make('#third'),
    ]);

    expect(array_map(
        fn (Step $step): string => $step->getSelector(),
        $tour->getSteps(),
    ))->toBe(['#first', '#second', '#third']);
});

it('rejects an id that is empty or only whitespace', function (string $id) {
    Tour::make($id);
})->with(['', '  '])->throws(InvalidArgumentException::class);

it('rejects anything that is not a step', function () {
    Tour::make('a')->steps([Step::make('#a'), 'not a step']);
})->throws(InvalidArgumentException::class, 'a');
