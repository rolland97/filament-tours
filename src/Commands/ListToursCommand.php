<?php

namespace Rolland\FilamentTours\Commands;

use Filament\Facades\Filament;
use Illuminate\Console\Command;
use Rolland\FilamentTours\FilamentToursPlugin;
use Rolland\FilamentTours\TourRegistry;

/**
 * An inventory of a panel's tours.
 *
 * Deliberately does not validate selectors, and says nothing that implies it
 * has. A selector cannot be checked without a browser, so an honest listing is
 * preferred to a validator that overpromises (design §7).
 */
class ListToursCommand extends Command
{
    protected $signature = 'tours:list {--panel= : The panel to inspect; defaults to the default panel}';

    protected $description = 'List the guided tours registered on a Filament panel';

    public function handle(): int
    {
        $panelId = $this->option('panel') ?: Filament::getDefaultPanel()->getId();

        if (! is_string($panelId)) {
            $this->components->error('Could not determine which panel to inspect.');

            return static::FAILURE;
        }

        $key = FilamentToursPlugin::registryKey($panelId);

        if (! app()->bound($key)) {
            $this->components->error(
                "No tours are registered for panel [{$panelId}]. Is FilamentToursPlugin added to it?",
            );

            return static::FAILURE;
        }

        /** @var TourRegistry $registry */
        $registry = app($key);

        $tours = $registry->all();

        if ($tours === []) {
            $this->components->warn("Panel [{$panelId}] has the plugin registered but no tours defined.");

            return static::SUCCESS;
        }

        // Deliberately not $this->table(): Symfony wraps cells to the terminal
        // width, which splits a fully-qualified page class across lines and
        // makes it unusable for copying or grepping — the one thing a developer
        // reading this list actually wants.
        $this->newLine();
        $this->line("Tours registered on panel [{$panelId}]:");
        $this->newLine();

        foreach ($tours as $tour) {
            $applies = $tour->getPageClass()
                ?? ($tour->getPredicate() !== null ? 'when() predicate' : 'nothing — no page and no predicate');

            $this->line(sprintf(
                '  <options=bold>%s</>  (%d %s, once: %s)',
                $tour->getId(),
                count($tour->getSteps()),
                count($tour->getSteps()) === 1 ? 'step' : 'steps',
                $tour->isOnce() ? 'yes' : 'no',
            ));
            $this->line("      applies to: {$applies}");
        }

        $this->newLine();

        $this->components->info(
            'Step selectors are not checked: that needs a browser. This lists what is registered, nothing more.',
        );

        return static::SUCCESS;
    }
}
