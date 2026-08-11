<?php

namespace Rolland\FilamentTours\State;

use Rolland\FilamentTours\Contracts\TourState;

/**
 * The default: no server-side state at all.
 *
 * Both methods look like stubs and are not. Under this driver the browser holds
 * the answer in localStorage, under key filament-tours:{panel}:{tour}, so the
 * server genuinely does not know whether a tour has been seen. Answering false
 * is honest; answering anything else would be a guess dressed as a fact.
 *
 * The consequence, accepted by design: "seen" is per-browser, so a second
 * device replays the tour. Fine for hints. A host that needs "confirm you have
 * read this" must configure a server-side driver instead.
 */
final class LocalStorageState implements TourState
{
    public function hasSeen(string $tourId): bool
    {
        return false;
    }

    public function markSeen(string $tourId): void
    {
        // Intentionally empty. The browser writes localStorage; nothing to do here.
    }
}
