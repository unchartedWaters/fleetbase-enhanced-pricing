<?php

namespace Fleetbase\EnhancedPricing\Models;

/**
 * A change to a record that is kept as it was saved: a profile version, a manual charge other than
 * its removal, or a dated change other than its cancelling.
 */
final class ImmutableRecord extends \DomainException
{
    public function __construct(public readonly string $model)
    {
        parent::__construct("enhanced-pricing.store.immutable: $model");
    }
}
