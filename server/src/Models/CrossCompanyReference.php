<?php

namespace Fleetbase\EnhancedPricing\Models;

/**
 * A record that points at, or was handed, a record of another company, or at one that doesn't
 * exist. Another company's record reads as missing, so the message never says which it was.
 */
final class CrossCompanyReference extends \DomainException
{
    public function __construct(public readonly string $model, public readonly string $column)
    {
        parent::__construct("enhanced-pricing.store.not-found: $model.$column");
    }
}
