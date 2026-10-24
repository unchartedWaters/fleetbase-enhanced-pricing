<?php

namespace Fleetbase\EnhancedPricing\Support;

use Brick\Math\BigDecimal;
use Brick\Math\BigRational;
use Brick\Math\RoundingMode;

/**
 * Exact numbers. Profile numbers, measurements and quantities are parsed strictly, worked out as
 * fractions, and only a line's amount is rounded, half up to the cent.
 */
final class Exact
{
    /**
     * Up to 7 digits before the point and 6 after it, with no exponent, so a value can never be
     * large enough to slow arithmetic down.
     */
    private const NUMBER = '/^-?\d{1,7}(\.\d{1,6})?$/';

    /**
     * Rounded values are held to this, far above every cap, so a huge measurement still gives an
     * integer that the line cap then refuses.
     */
    public const LARGEST = 1000000000000000;

    /**
     * The canonical decimal string for an integer, a float or a numeric string, or null when the
     * value is not a number in the allowed form.
     */
    public static function canonical(mixed $value): ?string
    {
        if (is_int($value)) {
            $value = (string) $value;
        } elseif (is_float($value)) {
            if (is_finite($value) === false) {
                return null;
            }
            $value = self::floatText($value);
        }
        if (is_string($value) === false || preg_match(self::NUMBER, $value) !== 1) {
            return null;
        }

        return (string) BigDecimal::of($value)->stripTrailingZeros();
    }

    public static function rational(int|string $value): BigRational
    {
        return BigRational::of((string) $value);
    }

    /**
     * Rounds half up to an integer, for a line amount in cents or a whole unit.
     */
    public static function round(BigRational $value): int
    {
        return self::bounded($value)->toScale(0, RoundingMode::HALF_UP)->toInt();
    }

    public static function ceil(BigRational $value): int
    {
        return self::bounded($value)->toScale(0, RoundingMode::CEILING)->toInt();
    }

    /**
     * A value for a line name: at most 3 decimals, rounded half up, without trailing zeros.
     */
    public static function display(BigRational $value): string
    {
        return (string) $value->toScale(3, RoundingMode::HALF_UP)->stripTrailingZeros();
    }

    public static function isWhole(BigRational $value): bool
    {
        return $value->simplified()->getDenominator()->isEqualTo(1);
    }

    private static function bounded(BigRational $value): BigRational
    {
        return $value->isGreaterThan(self::LARGEST) ? BigRational::of(self::LARGEST) : $value;
    }

    /**
     * A float as the shortest decimal that reads back as the same float, such as "0.1" or
     * "0.00005", written out without an exponent. json_encode gives that form with PHP's default
     * serialize_precision of -1; (string) would round to 14 digits.
     */
    private static function floatText(float $value): string
    {
        $text = (string) json_encode($value);

        return stripos($text, 'e') === false ? $text : (string) BigDecimal::of($text)->stripTrailingZeros();
    }
}
