<?php

namespace Fleetbase\EnhancedPricing\Pricing;

/**
 * A manual charge on the order. A new charge (stored false) is priced from the profile: its
 * quantity, or for an entered charge its amount, is checked against the charge's limits. A stored
 * charge (stored true) bills the amount saved when it was added, whatever the profile now says.
 */
final class ManualChargeInput
{
    public function __construct(
        public readonly string $key,
        public readonly int|float|string|null $quantity = null,
        public readonly ?int $amount = null,
        public readonly bool $stored = false,
    ) {
    }
}
