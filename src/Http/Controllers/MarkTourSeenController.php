<?php

namespace Rolland\FilamentTours\Http\Controllers;

use Filament\Facades\Filament;
use Illuminate\Http\Response;
use Rolland\FilamentTours\Contracts\TourState;
use Rolland\FilamentTours\FilamentToursPlugin;
use Rolland\FilamentTours\Tour;
use Rolland\FilamentTours\TourRegistry;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The package's only write endpoint.
 *
 * It records and never reads: there is no GET counterpart, because "has this
 * user seen it" is answered during render and must not become queryable.
 *
 * Authentication is inherited from the panel, never reimplemented here — the
 * route is registered inside the panel's own middleware group.
 */
class MarkTourSeenController
{
    public function __invoke(string $tour): Response
    {
        $panelId = Filament::getCurrentPanel()?->getId();

        $key = $panelId === null ? null : FilamentToursPlugin::registryKey($panelId);

        if ($key === null || ! app()->bound($key)) {
            throw new NotFoundHttpException;
        }

        /** @var TourRegistry $registry */
        $registry = app($key);

        // The segment is validated against what is registered before it is used
        // for anything. It is not a free-form key a caller can write arbitrary
        // values into, and an unknown id must never reach the host's driver.
        $known = array_filter(
            $registry->all(),
            fn (Tour $candidate): bool => $candidate->getId() === $tour,
        );

        if ($known === []) {
            throw new NotFoundHttpException;
        }

        // Who "seen" refers to is the driver's business — it reads the
        // authenticated user itself if identity matters to it.
        app(TourState::class)->markSeen($tour);

        return response()->noContent();
    }
}
