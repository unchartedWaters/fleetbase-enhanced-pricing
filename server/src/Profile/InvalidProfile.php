<?php

namespace Fleetbase\EnhancedPricing\Profile;

/**
 * A profile that failed its checks, with every problem found.
 */
final class InvalidProfile extends \InvalidArgumentException
{
    /**
     * @param list<Problem> $problems
     */
    public function __construct(public readonly array $problems)
    {
        parent::__construct('enhanced-pricing.profile.invalid');
    }
}
