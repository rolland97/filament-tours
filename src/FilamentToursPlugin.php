<?php

namespace Rolland\FilamentTours;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;
use Rolland\FilamentTours\Contracts\TourState;
use Rolland\FilamentTours\Http\Controllers\MarkTourSeenController;

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

        // Exactly one route, and only when a host driver is configured. Under
        // the browser-local default none is registered at all (FR-020).
        // authenticatedRoutes(), not routes(): Filament wraps these in the
        // panel's own auth middleware, which is exactly the inheritance the
        // contract requires. routes() would publish this write endpoint
        // unauthenticated.
        if ($this->hasServerDriver()) {
            $panel->authenticatedRoutes(fn (): mixed => Route::post(
                'filament-tours/{tour}/seen',
                MarkTourSeenController::class,
            )->name('filament-tours.seen'));
        }
    }

    public function boot(Panel $panel): void
    {
        //
    }

    /**
     * Whether persistence lives on the server for this request.
     *
     * The browser-local default holds no server state, so there is nothing to
     * write and therefore no endpoint to register or attack.
     */
    protected function hasServerDriver(): bool
    {
        return config('filament-tours.state', 'local') !== 'local';
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
                // Filament prefixes panel route names with 'filament.' AND the
                // panel id, so the resolvable name is filament.{panel}.{name}.
                // __TOUR__ is a placeholder the client substitutes — the id is
                // only known in the browser, when a tour actually finishes.
                'seenEndpoint' => $this->hasServerDriver()
                    ? route("filament.{$panel->getId()}.filament-tours.seen", ['tour' => '__TOUR__'])
                    : null,
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
