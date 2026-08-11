<?php

namespace Rolland\FilamentTours\Tests\Support;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Foundation\Auth\User;

/**
 * The panel user the suite acts as.
 *
 * Implements FilamentUser deliberately. Filament allows a user model that does
 * not implement it **only** when config('app.env') === 'local' — so a plain
 * Illuminate user passes on a developer's machine and returns 403 everywhere
 * else. Every real host implements this contract; the harness now does too,
 * which is why these tests no longer depend on the ambient environment.
 */
class TestUser extends User implements FilamentUser
{
    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }
}
