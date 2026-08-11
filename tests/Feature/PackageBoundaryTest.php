<?php

/**
 * Constitution Principle II — the host owns its data and its words.
 *
 * These assert absence, which is unusual but deliberate: the skeleton shipped
 * both a migration and a lang file, and both are the kind of thing a helpful
 * future change re-adds without noticing it crosses a boundary.
 */
it('ships no migration', function () {
    $migrations = glob(__DIR__ . '/../database/migrations/*') ?: [];

    expect($migrations)->toBeEmpty(
        'The package must ship no migration: persistence is the host application\'s decision.',
    );
});

it('ships no translation files', function () {
    expect(is_dir(__DIR__ . '/../resources/lang'))->toBeFalse(
        'The package must ship no lang files: copy is plain strings the host has already translated.',
    );
});
