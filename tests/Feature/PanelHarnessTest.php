<?php

use Rolland\FilamentTours\Tests\Panel\Pages\PageA;
use Rolland\FilamentTours\Tests\Panel\Pages\PageB;

it('boots the test panel and routes both pages', function () {
    expect(filament()->getPanel('testing'))->not->toBeNull();

    $this->get(PageA::getUrl())->assertOk();
    $this->get(PageB::getUrl())->assertOk();
});
