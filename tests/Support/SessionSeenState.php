<?php

namespace Rolland\FilamentTours\Tests\Support;

use Rolland\FilamentTours\Contracts\TourState;

/**
 * A server-side driver that actually persists across requests.
 *
 * Used only for manual browser verification, where FakeServerState's static
 * arrays are useless because each request is a fresh process. A real host would
 * write to a column or a JSON blob; the session is the cheapest thing that
 * behaves the same way from the browser's point of view.
 */
class SessionSeenState implements TourState
{
    protected const KEY = 'filament-tours.seen';

    public function hasSeen(string $tourId): bool
    {
        return in_array($tourId, session()->get(static::KEY, []), true);
    }

    public function markSeen(string $tourId): void
    {
        $seen = session()->get(static::KEY, []);

        if (! in_array($tourId, $seen, true)) {
            $seen[] = $tourId;
        }

        session()->put(static::KEY, $seen);
        session()->save();
    }
}
