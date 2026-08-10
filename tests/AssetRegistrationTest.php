<?php

use Filament\Support\Facades\FilamentAsset;

/**
 * Both halves of delivery, not just the script.
 *
 * The tour engine ships its own stylesheet, and an unstyled tour is not a
 * working tour (SC-004). Registering a path that no build produces is the
 * failure mode the skeleton already shipped once, so assert the files exist.
 */
it('registers the alpine component against a file that exists', function () {
    $components = FilamentAsset::getAlpineComponents(['rolland97/filament-tours']);

    expect($components)->toHaveCount(1);

    $path = reset($components)->getPath();

    expect($path)->not->toBeNull()
        ->and(file_exists($path))->toBeTrue("Alpine component missing on disk: {$path}");
});

it('registers the tour engine stylesheet against a file that exists', function () {
    $styles = FilamentAsset::getStyles(['rolland97/filament-tours']);

    expect($styles)->toHaveCount(1);

    $path = reset($styles)->getPath();

    expect($path)->not->toBeNull()
        ->and(file_exists($path))->toBeTrue("Stylesheet missing on disk: {$path}");
});
