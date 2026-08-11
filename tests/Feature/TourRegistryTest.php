<?php

use Rolland\FilamentTours\State\LocalStorageState;
use Rolland\FilamentTours\Step;
use Rolland\FilamentTours\Tests\Panel\Pages\PageA;
use Rolland\FilamentTours\Tests\Panel\Pages\PageB;
use Rolland\FilamentTours\Tour;
use Rolland\FilamentTours\TourRegistry;

function registry(Tour ...$tours): TourRegistry
{
    $registry = new TourRegistry(new LocalStorageState);
    $registry->register(...$tours);

    return $registry;
}

function tour(string $id): Tour
{
    return Tour::make($id)->steps([Step::make('#a')]);
}

it('resolves a tour whose page class is in scope', function () {
    $resolved = registry(tour('a')->for(PageA::class))->resolveFor([PageA::class]);

    expect($resolved)->toHaveCount(1)
        ->and($resolved[0]->getId())->toBe('a');
});

it('does not resolve a tour for a page it does not target', function () {
    expect(registry(tour('a')->for(PageA::class))->resolveFor([PageB::class]))->toBeEmpty();
});

it('resolves a tour whose predicate says yes, on any page', function () {
    $registry = registry(tour('a')->when(fn (): bool => true));

    expect($registry->resolveFor([PageA::class]))->toHaveCount(1)
        ->and($registry->resolveFor([PageB::class]))->toHaveCount(1);
});

it('does not resolve a tour whose predicate says no', function () {
    expect(registry(tour('a')->when(fn (): bool => false))->resolveFor([PageA::class]))->toBeEmpty();
});

it('resolves a tour if either its page or its predicate matches', function () {
    $registry = registry(tour('a')->for(PageA::class)->when(fn (): bool => false));

    expect($registry->resolveFor([PageA::class]))->toHaveCount(1);
});

it('never resolves a tour with neither a page nor a predicate', function () {
    expect(registry(tour('a'))->resolveFor([PageA::class]))->toBeEmpty();
});

it('resolves nothing when the scope list is empty', function () {
    expect(registry(tour('a')->for(PageA::class))->resolveFor([]))->toBeEmpty();
});

it('keeps resolved tours in registration order', function () {
    $registry = registry(
        tour('first')->for(PageA::class),
        tour('second')->for(PageA::class),
        tour('third')->for(PageA::class),
    );

    expect(array_map(
        fn (Tour $tour): string => $tour->getId(),
        $registry->resolveFor([PageA::class]),
    ))->toBe(['first', 'second', 'third']);
});

/*
 * FR-017 / SC-007. Each of these must name the offending tour: a message that
 * says only "duplicate id" leaves the developer grepping a panel provider.
 */
it('refuses two tours sharing an id, naming the offender', function () {
    registry(tour('itrf-create')->for(PageA::class), tour('itrf-create')->for(PageB::class));
})->throws(InvalidArgumentException::class, 'itrf-create');

it('refuses a tour with no steps, naming the offender', function () {
    registry(Tour::make('empty-tour')->for(PageA::class));
})->throws(InvalidArgumentException::class, 'empty-tour');

it('refuses a page class that does not exist, naming the tour and the class', function () {
    registry(tour('typo-tour')->for('App\\Filament\\Pages\\Mispelled'));
})->throws(InvalidArgumentException::class, 'typo-tour');

it('names the missing class too, not just the tour', function () {
    registry(tour('typo-tour')->for('App\\Filament\\Pages\\Mispelled'));
})->throws(InvalidArgumentException::class, 'Mispelled');

it('accepts a tour whose page class exists', function () {
    expect(registry(tour('fine')->for(PageA::class))->all())->toHaveCount(1);
});

it('accepts a predicate-only tour, which has no class to check', function () {
    expect(registry(tour('fine')->when(fn (): bool => true))->all())->toHaveCount(1);
});

it('lists every registered tour in registration order', function () {
    $registry = registry(tour('one')->for(PageA::class), tour('two')->when(fn (): bool => true));

    expect(array_map(fn (Tour $tour): string => $tour->getId(), $registry->all()))
        ->toBe(['one', 'two']);
});
