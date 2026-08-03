<?php

namespace Rolland\FilamentTours\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Rolland\FilamentTours\FilamentTours
 */
class FilamentTours extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Rolland\FilamentTours\FilamentTours::class;
    }
}
