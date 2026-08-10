<?php

namespace Rolland\FilamentTours\Tests\Panel;

use Filament\Panel;
use Filament\PanelProvider;
use Rolland\FilamentTours\FilamentToursPlugin;
use Rolland\FilamentTours\Step;
use Rolland\FilamentTours\Tests\Panel\Pages\PageA;
use Rolland\FilamentTours\Tests\Panel\Pages\PageB;
use Rolland\FilamentTours\Tour;

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
                    Tour::make('page-a-tour')
                        ->for(PageA::class)
                        ->once()
                        ->steps([
                            Step::make('[data-tour="thing"]')
                                ->title('Thing')
                                ->body('This is the thing.'),
                            Step::make('[data-tour="other"]')
                                ->title('Other')
                                // Deliberately hostile copy: hosts interpolate values into
                                // this, so the escaping guarantee needs something to prove.
                                ->body('And the other. <script>alert(1)</script>')
                                ->side('left'),
                        ]),

                    // Predicate-driven and NOT run-once, so the suite can prove
                    // both the escape hatch and FR-026 on the same page.
                    Tour::make('page-b-repeating')
                        ->when(fn (): bool => request()->boolean('repeating'))
                        ->steps([
                            Step::make('[data-tour="thing"]')->title('Again')->body('Every visit.'),
                        ]),
                ]),
            );
    }
}
