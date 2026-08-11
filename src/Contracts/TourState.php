<?php

namespace Rolland\FilamentTours\Contracts;

/**
 * Where "this user has seen this tour" is recorded.
 *
 * Deliberately per-tour and silent about users: which user "seen" refers to is
 * the implementation's business, and it can read auth()->user() itself. Passing
 * a user in would force an opinion about identity this package has no right to
 * hold.
 */
interface TourState
{
    public function hasSeen(string $tourId): bool;

    public function markSeen(string $tourId): void;
}
