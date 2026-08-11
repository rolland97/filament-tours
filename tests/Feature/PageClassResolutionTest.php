<?php

use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Rolland\FilamentTours\Tests\Panel\Pages\PageA;
use Rolland\FilamentTours\Tests\Panel\Pages\PageB;

/**
 * The design's section 6 tripwire.
 *
 * Page-class resolution is the one seam that depends on how Filament models a
 * page. The design assumed the class had to be read off the current route's
 * action — an internal detail an upgrade could move. It does not: Filament
 * hands the class to render hooks through HasRenderHookScopes.
 *
 * If a Filament upgrade stops doing that, this fails loudly here instead of
 * silently showing no tours anywhere.
 *
 * It observes the hook directly rather than asserting on rendered output,
 * because the page class must never reach the browser (FR-007) — so there is
 * deliberately nothing in the HTML to assert against.
 *
 * @return array<int, array<string>>
 */
function capturedScopesOn(string $url): array
{
    $captured = [];

    FilamentView::registerRenderHook(
        PanelsRenderHook::BODY_END,
        function (array $scopes) use (&$captured): string {
            $captured[] = $scopes;

            return '';
        },
    );

    test()->get($url)->assertOk();

    return $captured;
}

it('hands the current page class to a body-end render hook', function () {
    $captured = capturedScopesOn(PageA::getUrl());

    expect($captured)->not->toBeEmpty()
        ->and($captured[0])->toContain(PageA::class);
});

it('hands a different page class on a different page', function () {
    $captured = capturedScopesOn(PageB::getUrl());

    expect($captured)->not->toBeEmpty()
        ->and($captured[0])->toContain(PageB::class)
        ->and($captured[0])->not->toContain(PageA::class);
});

/*
 * There is deliberately no test here asserting the page class is absent from
 * the HTML. Livewire publishes it itself, in wire:name and in its snapshot
 * memo, on every Filament page — so such a test would fail for reasons this
 * package neither causes nor can fix.
 *
 * What this package does promise is narrower and is asserted in PayloadTest:
 * no page class and no predicate reach the browser *in the tour payload*.
 */
