<?php

use Rolland\FilamentTours\Contracts\TourState;
use Rolland\FilamentTours\Tests\Panel\Pages\PageA;
use Rolland\FilamentTours\Tests\Panel\Pages\PageB;
use Rolland\FilamentTours\Tests\Support\FakeServerState;

it('resolves the host driver named in config', function () {
    expect(app(TourState::class))->toBeInstanceOf(FakeServerState::class);
});

it('advertises the seen endpoint in the payload', function () {
    $payload = payloadFrom(PageA::getUrl());

    expect($payload['seenEndpoint'])->toBeString()
        ->and($payload['seenEndpoint'])->toContain('filament-tours');
});

it('filters a seen tour out before render, so its copy never reaches the browser', function () {
    FakeServerState::$seen = ['page-a-tour'];

    $response = $this->get(PageA::getUrl())->assertOk();

    // Not merely hidden — absent. The copy must not be in the HTML at all.
    $response->assertDontSee('page-a-tour', escape: false)
        ->assertDontSee('This is the thing.', escape: false);

    expect(payloadFrom(PageA::getUrl())['tours'])->toBe([]);
});

it('still sends a tour the driver has not seen', function () {
    expect(payloadFrom(PageA::getUrl())['tours'])->toHaveCount(1);
});

it('does not filter tours that are not run-once', function () {
    // A repeating tour has no "seen" concept; the driver must not be consulted
    // in a way that could suppress it.
    FakeServerState::$seen = ['page-b-repeating'];

    expect(payloadFrom(PageB_url())['tours'])->toHaveCount(1);
});

function PageB_url(): string
{
    return PageB::getUrl() . '?repeating=1';
}
