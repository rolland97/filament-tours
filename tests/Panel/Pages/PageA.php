<?php

namespace Rolland\FilamentTours\Tests\Panel\Pages;

use Filament\Pages\Page;

/**
 * A panel page that tours in the test suite target.
 *
 * Deliberately trivial: the point is that it is a real routed Filament page,
 * so render-hook scopes carry its class the way they would in a host app.
 */
class PageA extends Page
{
    protected static string $routePath = '/page-a';

    protected string $view = 'filament-tours-tests::test-page';
}
