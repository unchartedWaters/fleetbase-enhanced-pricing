<?php

namespace Fleetbase\EnhancedPricing\Pricing;

/**
 * One invoice line: its product code, name, billing code, quantity and unit amount in minor units.
 * The billing code is the code the line is billed under: the product's code, or a route's own code
 * when its service rate reads one from the rate card row. The amount is always quantity × unit
 * amount. Sources name the rules that made it, such as "fee:heavy".
 */
final class QuoteLine
{
    /**
     * @param list<string> $sources
     */
    public function __construct(
        public readonly string $product,
        public readonly string $name,
        public readonly string $billingCode,
        public readonly int $quantity,
        public readonly int $unitAmount,
        public readonly array $sources,
    ) {
    }

    public function amount(): int
    {
        return $this->quantity * $this->unitAmount;
    }

    /**
     * @return array{product: string, billing_code: string, name: string, quantity: int, unit_amount: int, amount: int, sources: list<string>}
     */
    public function toArray(): array
    {
        return [
            'product'      => $this->product,
            'billing_code' => $this->billingCode,
            'name'         => $this->name,
            'quantity'     => $this->quantity,
            'unit_amount'  => $this->unitAmount,
            'amount'       => $this->amount(),
            'sources'      => $this->sources,
        ];
    }
}
