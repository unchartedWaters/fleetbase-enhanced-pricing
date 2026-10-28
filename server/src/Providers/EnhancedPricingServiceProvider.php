<?php

namespace Fleetbase\EnhancedPricing\Providers;

use Fleetbase\Providers\CoreServiceProvider;

/**
 * Loads the extension's tables and the console's routes. Commands are added here as the later
 * parts of the plan arrive.
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
     * it a second time, and loads only its own migrations and routes.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../migrations');
        $this->loadRoutesFrom(__DIR__ . '/../routes.php');
    }
}
