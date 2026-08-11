<?php

namespace Rolland\FilamentTours\Actions;

use Filament\Actions\Action;
use Illuminate\Support\Js;

/**
 * A control that replays a tour on demand.
 *
 * The action's name is the tour id, which is what `StartTourAction::make('x')`
 * reads like at a call site.
 *
 * Entirely client-side: the tour's payload is already on the page, so a server
 * round trip would fetch data we are holding. It is a convenience over the
 * documented `filament-tours:start` event, not a second mechanism.
 */
class StartTourAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->label(fn (): string => __('Show me around'));

        // Js::from() rather than string interpolation: this value is placed
        // inside an Alpine expression, and a tour id containing a quote would
        // otherwise close the string and append whatever followed it. Ids are
        // developer-authored, so this is defence in depth rather than a fix.
        $this->alpineClickHandler(
            fn (): string => "\$dispatch('filament-tours:start', { tour: " . Js::from($this->getName()) . ' })',
        );
    }
}
