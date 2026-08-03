<?php

namespace Rolland\FilamentTours\Commands;

use Illuminate\Console\Command;

class FilamentToursCommand extends Command
{
    public $signature = 'filament-tours';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
