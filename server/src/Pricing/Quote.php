<?php

namespace Fleetbase\EnhancedPricing\Pricing;

/**
 * A priced order: its lines in the order they were made, notes about anything not checked, and
 * what it was priced with, so the price can be reproduced.
 */
final class Quote
{
    /**
     * @param list<QuoteLine>                                              $lines
     * @param list<array{key: string, params: array<string, scalar|null>}> $notes
     * @param array<string, mixed>                                         $meta
     */
    public function __construct(
        public readonly string $currency,
        public readonly array $lines,
        public readonly array $notes,
        public readonly array $meta,
    ) {
    }

    public function total(): int
    {
        return array_sum(array_map(static fn (QuoteLine $line): int => $line->amount(), $this->lines));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'currency' => $this->currency,
            'total'    => $this->total(),
            'lines'    => array_map(static fn (QuoteLine $line): array => $line->toArray(), $this->lines),
            'notes'    => $this->notes,
            'meta'     => $this->meta,
        ];
    }
}
