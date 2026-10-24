<?php

namespace Fleetbase\EnhancedPricing\Pricing;

/**
 * Everything one order is priced from. The caller supplies the pickup's timezone (from the
 * pickup place's location) and the driving distance, so the engine itself never looks anything
 * up.
 */
final class PricingRequest
{
    /**
     * @param array<string, mixed>                                            $profile       the profile as saved
     * @param list<EntityInput>                                               $entities
     * @param array<string, mixed>                                            $attributes    the order's attributes
     * @param array<string, mixed>|null                                       $row           the chosen rate card row's values
     * @param list<ManualChargeInput>                                         $manualCharges
     * @param array<string, mixed>                                            $overrides     the customer's overrides
     * @param list<array{path: string, value: mixed, effective_from: string}> $valueChanges  dated changes to global values
     * @param array<string, string>                                           $labels        customer, card and row, for messages
     */
    public function __construct(
        public readonly array $profile,
        public readonly string $serviceRate,
        public readonly \DateTimeImmutable $pickupAt,
        public readonly string $timezone,
        public readonly array $entities = [],
        public readonly array $attributes = [],
        public readonly ?array $row = null,
        public readonly ?int $distanceMeters = null,
        public readonly array $manualCharges = [],
        public readonly array $overrides = [],
        public readonly array $valueChanges = [],
        public readonly array $labels = [],
    ) {
    }
}
