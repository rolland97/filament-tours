<?php

use Rolland\FilamentTours\Tests\Panel\Pages\PageA;
use Rolland\FilamentTours\Tests\Panel\Pages\PageB;

/**
 * Pull the Alpine component's initial state back out of the rendered page.
 *
 * Asserting on the decoded payload rather than on substrings keeps these tests
 * honest about shape: a test that greps for a tour id passes even if the id is
 * sitting in the wrong field.
 *
 * @return array<string, mixed>
 */
function payloadFrom(string $url): array
{
    $html = (string) test()->get($url)->assertOk()->getContent();

    // Js::from() escapes every quote as " so the value is attribute-safe,
    // which is what makes [^"]* a sound way to grab it.
    preg_match('/x-data="filamentTours\((.*?)\)"/s', $html, $matches);

    expect($matches[1] ?? null)->not->toBeNull('No filamentTours() payload found in the response.');

    // Js::from() renders as JSON.parse('…'); unwrap it to get at the JSON.
    preg_match("/^JSON\.parse\('(.*)'\)$/s", html_entity_decode($matches[1], ENT_QUOTES), $inner);

    expect($inner[1] ?? null)->not->toBeNull('Payload was not the expected JSON.parse(...) form.');

    // Two decodes, not one. Js::from() escapes every quote as " so the value
    // survives an HTML attribute; the browser's string literal resolves those
    // before JSON.parse sees them, so PHP has to do the same. Decoding it as a
    // JSON *string* first turns " back into ", leaving real JSON behind.
    $json = json_decode('"' . $inner[1] . '"');

    $decoded = json_decode((string) $json, true);

    expect($decoded)->toBeArray();

    /** @var array<string, mixed> $decoded */
    return $decoded;
}

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

it('keeps a tour without the run-once flag in the payload, unmarked', function () {
    // FR-026: `once` is persistence, not a trigger. A tour lacking it is still
    // sent and still auto-starts — every visit, which is the point.
    $tours = payloadFrom(PageB::getUrl() . '?repeating=1')['tours'];

    expect($tours)->toHaveCount(1)
        ->and($tours[0]['id'])->toBe('page-b-repeating')
        ->and($tours[0]['once'])->toBeFalse();
});
