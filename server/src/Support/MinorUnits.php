<?php

namespace Fleetbase\EnhancedPricing\Support;

use Brick\Math\BigDecimal;

/**
 * Money is an integer count of the currency's ISO 4217 minor unit: cents for USD, whole yen for
 * JPY, thousandths for KWD. These are the exponents moneyphp (which Fleetbase formats money
 * with) uses; the console keeps the same table in addon/utils/money.js.
 */
final class MinorUnits
{
    /** ISO 4217 minor units that aren't 2. Any other code, known or not, has 2. */
    public const NOT_TWO = [
        'BIF' => 0, 'CLP' => 0, 'DJF' => 0, 'GNF' => 0, 'ISK' => 0, 'JPY' => 0, 'KMF' => 0, 'KRW' => 0,
        'PYG' => 0, 'RWF' => 0, 'UGX' => 0, 'UYI' => 0, 'VND' => 0, 'VUV' => 0, 'XAF' => 0, 'XOF' => 0,
        'XPF' => 0,
        'BHD' => 3, 'IQD' => 3, 'JOD' => 3, 'KWD' => 3, 'LYD' => 3, 'OMR' => 3, 'TND' => 3,
        'CLF' => 4, 'UYW' => 4,
    ];

    /** The decimals in the currency's minor unit: 0 for JPY, 2 for USD, 3 for KWD. */
    public static function exponent(string $currency): int
    {
        return self::NOT_TWO[strtoupper($currency)] ?? 2;
    }

    /**
     * An amount in minor units as a plain decimal in its currency, with no symbol or grouping:
     * 39500 is "395.00" in USD and "39500" in JPY.
     */
    public static function decimal(int $amount, string $currency): string
    {
        return (string) BigDecimal::ofUnscaledValue($amount, self::exponent($currency));
    }
}
