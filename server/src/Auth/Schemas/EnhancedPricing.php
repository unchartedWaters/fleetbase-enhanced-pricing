<?php

namespace Fleetbase\EnhancedPricing\Auth\Schemas;

/**
 * The extension's permissions, made by `php artisan fleetbase:create-permissions`: "see extension"
 * and the three below, each prefixed with the schema name (decision 21). Fleetbase permissions
 * are three words, "service action resource", so each resource is one hyphenated word.
 */
class EnhancedPricing
{
    public string $name = 'enhanced-pricing';

    public string $policyName = 'EnhancedPricing';

    /** @var list<string> */
    public array $guards = ['sanctum'];

    /**
     * "manage pricing-profile": the profile editor and dated changes. "manage rate-card":
     * customers, overrides, cards and rows. "manage manual-charge": charges on orders.
     *
     * @var list<string>
     */
    public array $permissions = ['manage pricing-profile', 'manage rate-card', 'manage manual-charge'];

    /** @var list<array<string, mixed>> */
    public array $resources = [];

    /** @var list<array{name: string, description: string, permissions: list<string>}> */
    public array $policies = [
        [
            'name'        => 'EnhancedPricingManager',
            'description' => 'Manage pricing profiles, rate cards and manual charges.',
            'permissions' => ['see extension', 'manage pricing-profile', 'manage rate-card', 'manage manual-charge'],
        ],
        [
            'name'        => 'EnhancedPricingRateCards',
            'description' => 'See pricing profiles and manage customers\' rate cards.',
            'permissions' => ['see extension', 'manage rate-card'],
        ],
    ];
}
