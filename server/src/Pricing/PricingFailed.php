<?php

namespace Fleetbase\EnhancedPricing\Pricing;

/**
 * Pricing stopped. The message is a translation key under enhanced-pricing.pricing, and the
 * parameters name what was wrong, so the order can show why it has no price.
 */
final class PricingFailed extends \RuntimeException
{
    /**
     * @param array<string, scalar|null> $params
     */
    public function __construct(public readonly string $key, public readonly array $params = [])
    {
        parent::__construct($key);
    }

    /**
     * @param array<string, scalar|null> $params
     */
    public static function because(string $name, array $params = []): self
    {
        return new self('enhanced-pricing.pricing.' . $name, $params);
    }
}
