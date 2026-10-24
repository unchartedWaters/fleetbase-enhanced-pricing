<?php

namespace Fleetbase\EnhancedPricing\Providers;

use Fleetbase\Providers\CoreServiceProvider;

if (class_exists(CoreServiceProvider::class) === false) {
    throw new \Exception('Extension cannot be loaded without `fleetbase/core-api` installed!');
}

/**
 * The pricing engine is plain PHP with no state, so this release registers nothing of its own.
 * Tables, routes and commands are added here as the later parts of the plan arrive.
 */
class EnhancedPricingServiceProvider extends CoreServiceProvider
{
    /**
     * Core observers stay registered by CoreServiceProvider. Leaving this inherited would register
     * them again from this extension.
     *
     * @var array<string, class-string>
     */
    public $observers = [];

    /**
     * @return void
     */
    public function register()
    {
        $this->app->register(CoreServiceProvider::class);
    }

    /**
     * CoreServiceProvider::boot reloads core routes and migrations, so this provider does not call
     * it a second time.
     *
     * @return void
     */
    public function boot()
    {
    }
}
