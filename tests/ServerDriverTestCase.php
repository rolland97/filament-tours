<?php

namespace Rolland\FilamentTours\Tests;

use Rolland\FilamentTours\Tests\Support\FakeServerState;

/**
 * Boots the panel with a host-supplied state driver configured.
 *
 * A separate TestCase rather than a config tweak inside a test body, because
 * route registration happens during panel boot — long before a test method
 * runs. Setting the config afterwards would be too late to change anything.
 */
class ServerDriverTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        FakeServerState::reset();
    }

    public function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('filament-tours.state', FakeServerState::class);
    }
}
