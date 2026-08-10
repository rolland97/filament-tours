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

    /**
     * The container key holding a panel's registry.
     *
     * Per-panel because the plugin is per-panel, and shared because the registry
     * holds definitions only — predicates are evaluated inside resolveFor(), at
     * request time, not here.
     */
    public static function registryKey(string $panelId): string
    {
        return "filament-tours.registry.{$panelId}";
    }

    public function register(Panel $panel): void
    {
        app()->singleton(
            static::registryKey($panel->getId()),
            function (): TourRegistry {
                $registry = new TourRegistry($this->resolveState());
                $registry->register(...$this->tours);

                return $registry;
            },
        );

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
        /** @var TourRegistry $registry */
        $registry = app(static::registryKey($panel->getId()));

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
