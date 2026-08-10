<?php

use Rolland\FilamentTours\Tests\Panel\Pages\PageA;
use Rolland\FilamentTours\Tests\Panel\Pages\PageB;

/**
 * The design's section 6 tripwire.
 *
 * Page-class resolution is the one seam that depends on how Filament models a
 * page. If a Filament upgrade stops handing the page class to render hooks,
 * this test fails loudly here instead of silently showing no tours anywhere.
 */
it('receives the current page class in the render hook scopes', function () {
    $this->get(PageA::getUrl())
        ->assertOk()
        ->assertSee('data-filament-tours-scopes="' . PageA::class . '"', escape: false);
});

it('receives a different page class on a different page', function () {
    $this->get(PageB::getUrl())
        ->assertOk()
        ->assertSee('data-filament-tours-scopes="' . PageB::class . '"', escape: false);
});
