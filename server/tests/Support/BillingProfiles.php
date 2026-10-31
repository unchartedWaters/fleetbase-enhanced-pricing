<?php

namespace Fleetbase\EnhancedPricing\Tests\Support;

use Fleetbase\EnhancedPricing\Pricing\Quote;
use Fleetbase\EnhancedPricing\Pricing\QuoteLine;

/**
 * The example profile with a `billing_code` column on its rate table, read by the tiered rate.
 */
final class BillingProfiles
{
    /**
     * Subtotal fees are off unless asked for, so tests see only the lines they name.
     *
     * @return array<string, mixed>
     */
    public static function profile(bool $subtotalFees = false): array
    {
        $profile = Profiles::append(Profiles::example(), 'rate_tables.0.columns', [
            'key'  => 'billing_code',
            'type' => 'text',
        ]);
        $profile = Profiles::set($profile, 'service_rates.0.billing_code_column', 'billing_code');
        if ($subtotalFees === false) {
            foreach ([0, 1, 2] as $index) {
                $profile = Profiles::set($profile, "subtotal_fees.$index.enabled", false);
            }
        }

        return $profile;
    }

    /**
     * The quote's lines as [product, billing code, name, amount].
     *
     * @return list<array{0: string, 1: string, 2: string, 3: int}>
     */
    public static function lines(Quote $quote): array
    {
        return array_map(
            static fn (QuoteLine $line): array => [$line->product, $line->billingCode, $line->name, $line->amount()],
            $quote->lines,
        );
    }
}
