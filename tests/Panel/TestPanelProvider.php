<?php

namespace Rolland\FilamentTours\Tests\Panel;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Auth\User;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;
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

        // Browser-verification convenience: the panel declares auth middleware
        // like a real one, so `testbench serve` needs a way in. Lives here, in
        // tests/, which is export-ignored and can never reach a consumer.
        Route::get('/testing-login', function () {
            $user = new User;
            $user->forceFill(['id' => 1, 'name' => 'Test User', 'email' => 'test@example.test']);

            Auth::login($user);

            return redirect('/testing/page-a');
        });
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('testing')
            ->path('testing')
            // A real panel declares both. Filament does NOT add auth middleware
            // on its own, and the seen route inherits exactly what the panel
            // declares — so a panel with no authMiddleware protects nothing,
            // its own pages included.
            // The stack a generated Filament panel declares. Without it there is
            // no session and no CSRF, so anything depending on either silently
            // does nothing — which is exactly how the seen-write appeared to
            // succeed while recording nothing.
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->login()
            // Tests always run with auth on — that is how the seen route's
            // protection is asserted. `testbench serve` can turn it off, because
            // a persistent session login needs a real users table and the
            // browser checks are about client mechanics, not authentication.
            ->authMiddleware(env('FILAMENT_TESTS_NO_AUTH') ? [] : [Authenticate::class])
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

                    // Rot, staged. The middle step points at nothing, so the
                    // client must skip it and still run the other two (FR-014).
                    Tour::make('page-b-partial')
                        ->when(fn (): bool => request()->boolean('partial'))
                        ->steps([
                            Step::make('[data-tour="thing"]')->title('First')->body('Present.'),
                            Step::make('[data-tour="gone"]')->title('Missing')->body('Not on the page.'),
                            Step::make('[data-tour="other"]')->title('Third')->body('Also present.'),
                        ]),

                    // Total rot: nothing resolves, so the tour must not start
                    // at all rather than opening an empty overlay (FR-016).
                    Tour::make('page-b-missing')
                        ->when(fn (): bool => request()->boolean('missing'))
                        ->steps([
                            Step::make('[data-tour="gone"]')->title('Gone')->body('Nope.'),
                            Step::make('[data-tour="also-gone"]')->title('Also gone')->body('Nope.'),
                        ]),
                ]),
            );
    }
}
