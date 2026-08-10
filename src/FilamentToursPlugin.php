<?php

namespace Rolland\FilamentTours;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Rolland\FilamentTours\Contracts\TourState;

class FilamentToursPlugin implements Plugin
{
    /** @var list<Tour> */
    protected array $tours = [];

    public function getId(): string
    {
        return 'filament-tours';
    }

    /**
     * @param  array<int, Tour>  $tours
     */
    public function tours(array $tours): static
    {
        $this->tours = array_values($tours);

        return $this;
    }

    public function register(Panel $panel): void
    {
        $panel->renderHook(
            PanelsRenderHook::BODY_END,
            /** @param array<string> $scopes */
            fn (array $scopes): View => $this->render($panel, $scopes),
        );
    }

    public function boot(Panel $panel): void
    {
        //
    }

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }

    /**
     * @param  array<string>  $scopes
     */
    protected function render(Panel $panel, array $scopes): View
    {
        // Built per request, not per panel: predicates are evaluated during
        // resolveFor(), and they read request state.
        $registry = new TourRegistry($this->resolveState());
        $registry->register(...$this->tours);

        return view('filament-tours::tours', [
            'payload' => [
                'panel' => $panel->getId(),
                'debug' => (bool) config('app.debug'),
                // Null under the browser-local driver, and its nullness is how the
                // client knows which driver is active — no separate mode flag.
                'seenEndpoint' => null,
                'tours' => array_map(
                    fn (Tour $tour): array => $this->describe($tour),
                    $registry->resolveFor($scopes),
                ),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function describe(Tour $tour): array
    {
        return [
            'id' => $tour->getId(),
            'once' => $tour->isOnce(),
            'steps' => array_map(fn (Step $step): array => [
                'selector' => $step->getSelector(),
                'title' => $step->getTitle(),
                'body' => $step->getBody(),
                'side' => $step->getSide(),
                'align' => $step->getAlign(),
            ], $tour->getSteps()),
        ];
    }

    protected function resolveState(): TourState
    {
        return app(TourState::class);
    }
}
