<?php

it('registers the package config under the filament-tours key', function () {
    expect(config('filament-tours.state'))->toBe('local');
});
