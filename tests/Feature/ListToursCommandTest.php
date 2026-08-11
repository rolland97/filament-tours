<?php

// FR-018 / SC-006: an inventory of what is registered, which does not
// claim to have checked anything it cannot check.

use Rolland\FilamentTours\Tests\Panel\Pages\PageA;

it('lists every registered tour with its page, step count and run-once flag', function () {
    $this->artisan('tours:list')
        ->assertSuccessful()
        ->expectsOutputToContain('page-a-tour')
        ->expectsOutputToContain(PageA::class)
        ->expectsOutputToContain('page-b-repeating');
});

it('shows a predicate-driven tour as a predicate rather than a page', function () {
    $this->artisan('tours:list')
        ->assertSuccessful()
        ->expectsOutputToContain('when()');
});

it('makes no claim about whether selectors match anything', function () {
    // design §7: a selector cannot be checked without a browser, and an honest
    // listing beats a validator that implies more than it checks.
    $this->artisan('tours:list')
        ->assertSuccessful()
        ->doesntExpectOutputToContain('valid')
        ->doesntExpectOutputToContain('Valid');
});

it('says so plainly when a panel has no tours', function () {
    $this->artisan('tours:list', ['--panel' => 'empty'])
        ->assertFailed()
        ->expectsOutputToContain('empty');
});
