<?php

arch('no debugging helpers reach a consumer')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'die'])
    ->not->toBeUsed();

arch('contracts are interfaces, not classes to extend')
    ->expect('Rolland\FilamentTours\Contracts')
    ->toBeInterfaces();

arch('value objects are final, because inheriting one would widen the frozen API')
    ->expect(['Rolland\FilamentTours\Tour', 'Rolland\FilamentTours\Step'])
    ->toBeFinal();

arch('the package does not depend on its own test harness')
    ->expect('Rolland\FilamentTours')
    ->not->toUse('Rolland\FilamentTours\Tests');

/*
 * FR-003, and the only enforcement it will ever have.
 *
 * "MUST NOT provide helpers that encode the panel framework's internal markup"
 * is a negative requirement — nothing fails when someone adds Step::forField().
 * Such a helper reads better than a raw selector and breaks on a Filament point
 * release, which is exactly the trade the design rejected (D5).
 *
 * An allowlist, deliberately, not `->not->toUse('Filament')`. That form was
 * tried first and silently passed with a `Filament\Forms\Components\TextInput`
 * import sitting in Step — it does not match on namespace prefix, so it would
 * have been a guard in name only. This fails on *any* dependency not listed,
 * including ones nobody thought to forbid.
 */
arch('value objects depend on nothing but PHP itself')
    ->expect(['Rolland\FilamentTours\Tour', 'Rolland\FilamentTours\Step'])
    ->toOnlyUse(['Closure', 'InvalidArgumentException', 'Rolland\FilamentTours\Step']);
