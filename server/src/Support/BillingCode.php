<?php

namespace Fleetbase\EnhancedPricing\Support;

use Fleetbase\EnhancedPricing\Profile\ProfileReferences as R;
use Fleetbase\EnhancedPricing\Profile\Schema;

/**
 * A service rate's billing code, read from a text column of its rate card row. The engine and the
 * row check judge a code by these rules, so a row that saves is a row that prices: the code is
 * there, is written like a product code, and isn't another product's code in any case, so a
 * route's revenue never lands on a fee's code.
 */
final class BillingCode
{
    /**
     * Why the value can't be the rate's billing code: missing, invalid or is-product, else null.
     *
     * @param array<string, mixed> $profile a normalized profile
     * @param array<string, mixed> $rate    one of its service rates
     */
    public static function problem(mixed $value, array $profile, array $rate): ?string
    {
        if ($value === null || $value === '') {
            return 'missing';
        }
        if (is_string($value) === false || preg_match(Schema::CODE, $value) !== 1) {
            return 'invalid';
        }
        $own = R::text($rate['product']);
        foreach (R::items($profile['products'] ?? []) as $product) {
            $code = R::text($product['code']);
            if ($code !== $own && strtolower($code) === strtolower($value)) {
                return 'is-product';
            }
        }

        return null;
    }

    /**
     * The enabled service rates that read their billing code from a column of the table, by
     * column. A disabled rate prices no order; turning it on is a new version, which rechecks rows.
     *
     * @param array<string, mixed> $profile a normalized profile
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public static function ratesByColumn(array $profile, string $table): array
    {
        $rates = [];
        foreach (R::items($profile['service_rates'] ?? []) as $rate) {
            $column = $rate['billing_code_column'] ?? null;
            if (is_string($column) && ($rate['rate_table'] ?? null) === $table && ($rate['enabled'] ?? true) !== false) {
                $rates[$column][] = $rate;
            }
        }

        return $rates;
    }
}
