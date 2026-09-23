<?php

// FR-009: assets are bundled and registered through Filament, so a consumer
// configures no package manager and no bundler.

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

it('ships a dark variant for the popover, scoped to the class Filament toggles', function () {
    /*
     * FR-032: the tour engine paints its popover white and offers no dark mode,
     * so on a dark panel it arrives as a white card. Found by looking at a real
     * application; nothing in a test suite renders a colour.
     *
     * ⚠️ `.dark`, never `@media (prefers-color-scheme: dark)`. Filament keeps the
     * chosen appearance in localStorage and toggles `.dark` on <html>, so a media
     * query paints for the operating system while the panel believes otherwise.
     * This asserts the built stylesheet, not the source, because the built file
     * is what a consumer serves — an import dropped from the entry point would
     * leave the source rules right and the shipped file wrong.
     */
    $styles = FilamentAsset::getStyles(['rolland97/filament-tours']);

    $css = file_get_contents(reset($styles)->getPath());

    expect($css)->toContain('.dark .driver-popover')
        ->and($css)->not->toContain('prefers-color-scheme');
});
