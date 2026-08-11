<?php

use Rolland\FilamentTours\Tests\Panel\Pages\PageA;
use Rolland\FilamentTours\Tests\Panel\Pages\PageB;

it('carries the fields the payload contract promises', function () {
    $payload = payloadFrom(PageA::getUrl());

    expect($payload)->toHaveKeys(['panel', 'debug', 'seenEndpoint', 'tours'])
        ->and($payload['panel'])->toBe('testing')
        ->and($payload['debug'])->toBeBool()
        ->and($payload['tours'])->toBeArray();
});

it('sends no seen endpoint under the local driver', function () {
    expect(payloadFrom(PageA::getUrl())['seenEndpoint'])->toBeNull();
});

it('describes a matching tour in full', function () {
    $tours = payloadFrom(PageA::getUrl())['tours'];

    expect($tours)->toHaveCount(1);

    $tour = $tours[0];

    expect($tour)->toHaveKeys(['id', 'once', 'steps'])
        ->and($tour['id'])->toBe('page-a-tour')
        ->and($tour['once'])->toBeTrue()
        ->and($tour['steps'])->toHaveCount(2);

    expect($tour['steps'][0])->toHaveKeys(['selector', 'title', 'body', 'side', 'align'])
        ->and($tour['steps'][0]['selector'])->toBe('[data-tour="thing"]')
        ->and($tour['steps'][0]['title'])->toBe('Thing')
        ->and($tour['steps'][1]['side'])->toBe('left');
});

it('sends an empty tour list on a page nothing targets', function () {
    expect(payloadFrom(PageB::getUrl())['tours'])->toBe([]);
});

it('never leaks a page class or a predicate to the browser', function () {
    $encoded = json_encode(payloadFrom(PageA::getUrl()));

    expect($encoded)->toBeString()
        ->and((string) $encoded)->not->toContain('PageA')
        ->and((string) $encoded)->not->toContain('Closure')
        ->and((string) $encoded)->not->toContain('predicate');
});

it('renders copy as text, so markup in it cannot execute', function () {
    // The tour on PageA carries a step whose body contains a script tag.
    $html = $this->get(PageA::getUrl())->assertOk()->getContent();

    expect((string) $html)->not->toContain('<script>alert')
        ->and(payloadFrom(PageA::getUrl())['tours'][0]['steps'][1]['body'])
        ->toContain('<script>alert');
});

/*
 * FR-014 and FR-016 are client-side behaviours: whether a selector resolves is
 * only knowable in a browser, so the server cannot and must not try to decide.
 *
 * What the PHP suite can assert is the half that belongs to the server — it
 * sends the tour intact, unmatched selectors and all, and leaves the skipping
 * to the client. The skipping itself is browser-verified (quickstart Gate 2).
 */
it('sends a tour whose selectors may not resolve, and leaves that to the client', function () {
    $tours = payloadFrom(PageB::getUrl() . '?partial=1')['tours'];

    expect($tours)->toHaveCount(1)
        ->and($tours[0]['id'])->toBe('page-b-partial')
        ->and($tours[0]['steps'])->toHaveCount(3)
        ->and($tours[0]['steps'][1]['selector'])->toBe('[data-tour="gone"]');
});

it('sends a tour even when none of its selectors can resolve', function () {
    $tours = payloadFrom(PageB::getUrl() . '?missing=1')['tours'];

    expect($tours)->toHaveCount(1)
        ->and($tours[0]['id'])->toBe('page-b-missing')
        ->and($tours[0]['steps'])->toHaveCount(2);
});

it('keeps a tour without the run-once flag in the payload, unmarked', function () {
    // FR-026: `once` is persistence, not a trigger. A tour lacking it is still
    // sent and still auto-starts — every visit, which is the point.
    $tours = payloadFrom(PageB::getUrl() . '?repeating=1')['tours'];

    expect($tours)->toHaveCount(1)
        ->and($tours[0]['id'])->toBe('page-b-repeating')
        ->and($tours[0]['once'])->toBeFalse();
});

it('sends every matching tour, in registration order', function () {
    // FR-011 / FR-024: order is meaningful because it decides which tour
    // auto-starts. The rest stay in the payload so they can be replayed.
    $tours = payloadFrom(PageA::getUrl() . '?second=1')['tours'];

    expect(array_column($tours, 'id'))->toBe(['page-a-tour', 'page-a-second']);
});
