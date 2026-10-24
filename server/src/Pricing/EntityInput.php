<?php

namespace Fleetbase\EnhancedPricing\Pricing;

/**
 * One entity of the order's payload, as Fleet-Ops stores it: its entity type (from meta.rates),
 * weight and dimensions with their units, and the profile's entity attributes.
 */
final class EntityInput
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(
        public readonly ?string $type = null,
        public readonly int|float|string|null $weight = null,
        public readonly ?string $weightUnit = null,
        public readonly int|float|string|null $length = null,
        public readonly int|float|string|null $width = null,
        public readonly int|float|string|null $height = null,
        public readonly ?string $dimensionsUnit = null,
        public readonly array $attributes = [],
    ) {
    }
}
