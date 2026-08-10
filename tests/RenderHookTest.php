<?php

use Rolland\FilamentTours\Tests\Panel\Pages\PageA;
use Rolland\FilamentTours\Tests\Panel\Pages\PageB;

it('emits its render hook output into a panel page', function () {
    $this->get(PageA::getUrl())
        ->assertOk()
        ->assertSee('data-filament-tours', escape: false);
});

it('emits a matching tour on the page it targets', function () {
    $this->get(PageA::getUrl())
        ->assertOk()
        ->assertSee('page-a-tour', escape: false);
});

/**
 * SC-002, stated as strongly as it can be from the server side.
 *
 * Not just "the id is absent" — a user on a page they can reach must not
 * receive the copy, the selectors, or the id of a tour for a page they cannot.
 */
it('leaks nothing of a tour on a page it does not target', function () {
    $response = $this->get(PageB::getUrl())->assertOk();

    // Only strings the tour owns. The page's own markup carries a
    // data-tour="thing" element and the word "Thing", so asserting those
    // absent would be asserting the page away, not the tour.
    $response->assertDontSee('page-a-tour', escape: false)
        ->assertDontSee('This is the thing.', escape: false)
        ->assertDontSee('And the other.', escape: false);
});

it('still renders the component with an empty tour list on a non-targeted page', function () {
    // The component is always present; it is the payload that is empty. That
    // keeps replay working on pages with no auto-starting tour.
    $this->get(PageB::getUrl())
        ->assertOk()
        ->assertSee('data-filament-tours', escape: false);
});
