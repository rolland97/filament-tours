<?php

use Rolland\FilamentTours\Tests\ServerDriverTestCase;
use Rolland\FilamentTours\Tests\TestCase;

// Two scopes, deliberately non-overlapping: Pest refuses a second uses()->in()
// over a folder an earlier one already claimed, and ->in() is recursive.
uses(TestCase::class)->in('Feature');
uses(ServerDriverTestCase::class)->in('ServerDriver');

/**
 * Pull the Alpine component's initial state back out of a rendered page.
 *
 * Asserting on the decoded payload rather than on substrings keeps tests honest
 * about shape: one that greps for a tour id passes even if the id is sitting in
 * the wrong field.
 *
 * @return array<string, mixed>
 */
function payloadFrom(string $url): array
{
    $html = (string) test()->get($url)->assertOk()->getContent();

    // Js::from() escapes every quote as &quot; so the value is attribute-safe,
    // which is what makes this pattern sound.
    preg_match('/x-data="filamentTours\((.*?)\)"/s', $html, $matches);

    expect($matches[1] ?? null)->not->toBeNull('No filamentTours() payload found in the response.');

    // Js::from() renders as JSON.parse('…'); unwrap it to get at the JSON.
    preg_match("/^JSON\.parse\('(.*)'\)$/s", html_entity_decode($matches[1], ENT_QUOTES), $inner);

    expect($inner[1] ?? null)->not->toBeNull('Payload was not the expected JSON.parse(...) form.');

    // Two decodes, not one. The browser's string literal resolves " back to
    // a quote before JSON.parse sees it, so PHP has to do the same: decoding it
    // as a JSON *string* first leaves real JSON behind.
    $json = json_decode('"' . $inner[1] . '"');

    $decoded = json_decode((string) $json, true);

    expect($decoded)->toBeArray();

    /** @var array<string, mixed> $decoded */
    return $decoded;
}
