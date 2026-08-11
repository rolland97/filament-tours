<?php

namespace Rolland\FilamentTours\Tests\Support;

use Rolland\FilamentTours\Contracts\TourState;

/**
 * A host-supplied state driver, of the kind an application would write.
 *
 * State is static so a test can inspect what the driver was asked to do without
 * having to reach the exact instance the container resolved.
 */
class FakeServerState implements TourState
{
    /** @var list<string> */
    public static array $seen = [];

    /** @var list<string> */
    public static array $marked = [];

    public function hasSeen(string $tourId): bool
    {
        return in_array($tourId, static::$seen, true);
    }

    public function markSeen(string $tourId): void
    {
        static::$marked[] = $tourId;
    }

    public static function reset(): void
    {
        static::$seen = [];
        static::$marked = [];
    }
}
