<?php

namespace Fleetbase\EnhancedPricing\Pricing;

use Brick\Math\BigRational;

/**
 * An entity once its inputs are checked: its entity type, its weight in kilograms and volume in
 * cubic centimetres when known, its attributes, and the product it is billed under.
 */
final class PricedEntity
{
    public string $billingProduct = '';

    /**
     * @param array<string, mixed> $type
     * @param array<string, mixed> $attributes
     */
    public function __construct(
        public readonly int $number,
        public readonly array $type,
        public readonly ?BigRational $kilograms,
        public readonly ?BigRational $cubicCentimetres,
        public readonly array $attributes,
    ) {
    }
}
