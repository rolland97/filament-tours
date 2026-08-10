<?php

namespace Rolland\FilamentTours;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\View\PanelsRenderHook;

class FilamentToursPlugin implements Plugin
{
    /**
     * Spike shape only — replaced by a list of Tour value objects in T037.
     *
     * @var array<int, array{id: string, for: class-string}>
     */
    protected array $tours = [];

    public function getId(): string
    {
        return 'filament-tours';
    }

    /**
     * @param  array<int, array{id: string, for: class-string}>  $tours
     */
    public function tours(array $tours): static
    {
        $this->tours = $tours;

        return $this;
    }

    public function register(Panel $panel): void
    {
        $panel->renderHook(
            PanelsRenderHook::BODY_END,
            /** @param array<string> $scopes */
            fn (array $scopes): string => $this->render($scopes),
        );
    }

    /**
     * @param  array<string>  $scopes
     */
    protected function render(array $scopes): string
    {
        $applicable = array_values(array_filter(
            $this->tours,
            fn (array $tour): bool => in_array($tour['for'], $scopes, true),
        ));

        return sprintf(
            '<div data-filament-tours data-filament-tours-scopes="%s" data-filament-tours-payload="%s"></div>',
            e(implode(',', $scopes)),
            e((string) json_encode(array_column($applicable, 'id'))),
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
}
