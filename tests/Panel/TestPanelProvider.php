<?php

namespace Rolland\FilamentTours\Tests\Panel;

use Filament\Panel;
use Filament\PanelProvider;
use Rolland\FilamentTours\FilamentToursPlugin;
use Rolland\FilamentTours\Tests\Panel\Pages\PageA;
use Rolland\FilamentTours\Tests\Panel\Pages\PageB;

/**
 * The minimal panel the suite runs against.
 *
 * Two pages, no auth, no resources — just enough for a tour to match one page
 * and not the other. Tests that need the plugin registered add it themselves,
 * so this provider stays neutral about tours.
 */
class TestPanelProvider extends PanelProvider
{
    /**
     * Registered here, not in TestCase, so the panel also works under
     * `testbench serve` — TestCase::getEnvironmentSetUp never runs there.
     */
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/views', 'filament-tours-tests');
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('testing')
            ->path('testing')
            ->pages([
                PageA::class,
                PageB::class,
            ])
            ->plugin(
                FilamentToursPlugin::make()->tours([
                    ['id' => 'spike-tour', 'for' => PageA::class],
                ]),
            );
    }
}
