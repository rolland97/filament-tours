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
        ->assertSee('spike-tour', escape: false);
});

it('emits nothing of a tour on a page it does not target', function () {
    $this->get(PageB::getUrl())
        ->assertOk()
        ->assertDontSee('spike-tour', escape: false);
});
