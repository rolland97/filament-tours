<?php

// config for Rolland/FilamentTours

return [

    /*
    |--------------------------------------------------------------------------
    | Tour state driver
    |--------------------------------------------------------------------------
    |
    | Decides where "this user has seen this tour" is recorded.
    |
    | 'local'  — the browser decides, under localStorage key
    |            filament-tours:{panel}:{tour}. No server state is held and no
    |            route is registered. Per-browser, so a second device replays
    |            the tour: fine for hints, not for anything compliance-shaped.
    |
    | A class-string implementing Rolland\FilamentTours\Contracts\TourState —
    |            the host owns persistence. Seen tours are filtered out before
    |            render, and one route is registered behind the panel's own
    |            auth middleware to record them.
    |
    */

    'state' => env('FILAMENT_TOURS_STATE', 'local'),

];
