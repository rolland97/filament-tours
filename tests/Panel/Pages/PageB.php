<?php

namespace Rolland\FilamentTours\Tests\Panel\Pages;

use Filament\Pages\Page;

/**
 * The page no tour targets.
 *
 * Its whole job is to prove absence: a tour keyed to PageA must contribute
 * nothing at all to this page's response (SC-002).
 */
class PageB extends Page
{
    protected static string $routePath = '/page-b';

    protected string $view = 'filament-tours-tests::test-page';
}
