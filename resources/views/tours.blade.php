{{--
    The whole server-to-browser surface: one element carrying the payload as the
    Alpine component's initial state.

    No utility classes here on purpose. Anything Tailwind-shaped would force
    consumers to add a @source line to their panel theme so it survived purging,
    which contradicts SC-004's "without touching its panel theme". All visible
    styling comes from the tour engine's own stylesheet, registered as a Css asset.
--}}
<div
    data-filament-tours
    x-load
    x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('filament-tours', 'rolland97/filament-tours') }}"
    x-data="filamentTours({{ \Illuminate\Support\Js::from($payload) }})"
></div>
