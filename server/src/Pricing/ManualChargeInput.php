<?php

namespace Fleetbase\EnhancedPricing\Pricing;

/**
 * A manual charge on the order, in one of two shapes. A new charge is priced from the profile:
 * its quantity, or for an entered charge its amount, is checked against the charge's limits. An
 * added charge was priced when it was added and bills what was stored then, its product, line
 * name and amount, whatever the profile now says.
 */
final class ManualChargeInput
{
    private function __construct(
        public readonly string $key,
        public readonly int|float|string|null $quantity,
        public readonly ?int $amount,
        public readonly ?string $product,
        public readonly ?string $name,
    ) {
    }

    /**
     * A charge being added: its quantity when it takes one, its amount when the amount is entered.
     */
    public static function new(string $key, int|float|string|null $quantity = null, ?int $amount = null): self
    {
        return new self($key, $quantity, $amount, null, null);
    }

    /**
     * A charge already on the order, as stored when it was added.
     */
    public static function added(string $key, string $product, string $name, int $amount): self
    {
        return new self($key, null, $amount, $product, $name);
    }

    public function isAdded(): bool
    {
        return $this->product !== null;
    }
}
