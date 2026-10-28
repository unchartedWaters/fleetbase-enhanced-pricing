<?php

namespace Fleetbase\EnhancedPricing\Pricing;

use Brick\Math\BigRational;

/**
 * An entity once its inputs are checked: its entity type, its weight in kilograms and volume in
 * cubic centimetres when known, its attributes, the product it is billed under, and the label
 * messages name it by.
 */
final class PricedEntity
{
    public string $billingProduct = '';

    /**
     * @param array<string, mixed> $type
     * @param array<string, mixed> $attributes
     */
    public function __construct(
        public readonly array $type,
        public readonly ?BigRational $kilograms,
        public readonly ?BigRational $cubicCentimetres,
        public readonly array $attributes,
        public readonly string $label,
    ) {
    }
}
