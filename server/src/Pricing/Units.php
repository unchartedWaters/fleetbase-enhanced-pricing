<?php

namespace Fleetbase\EnhancedPricing\Pricing;

use Brick\Math\BigRational;
use Fleetbase\EnhancedPricing\Support\Exact;

/**
 * Exact conversions between Fleet-Ops' units and the units a parcel fee or a distance rate is
 * written in. Each factor is exact by definition (1 lb is 0.45359237 kg, 1 in is 2.54 cm).
 */
final class Units
{
    /** Kilograms in one unit. */
    public const WEIGHT = ['g' => '0.001', 'oz' => '0.028349523125', 'lb' => '0.45359237', 'kg' => '1'];

    /** Centimetres in one unit. */
    public const LENGTH = ['mm' => '0.1', 'cm' => '1', 'm' => '100', 'in' => '2.54', 'ft' => '30.48', 'yd' => '91.44'];

    /** Cubic centimetres in one unit. */
    public const VOLUME = ['cm3' => '1', 'm3' => '1000000', 'in3' => '16.387064', 'ft3' => '28316.846592'];

    /** Metres in one distance unit. */
    public const DISTANCE = ['km' => '1000', 'mi' => '1609.344'];

    public static function toKilograms(BigRational $value, string $unit): BigRational
    {
        return $value->multipliedBy(self::WEIGHT[$unit]);
    }

    public static function fromKilograms(BigRational $kilograms, string $unit): BigRational
    {
        return $kilograms->dividedBy(self::WEIGHT[$unit]);
    }

    public static function toCentimetres(BigRational $value, string $unit): BigRational
    {
        return $value->multipliedBy(self::LENGTH[$unit]);
    }

    public static function fromCubicCentimetres(BigRational $cubic, string $unit): BigRational
    {
        return $cubic->dividedBy(self::VOLUME[$unit]);
    }

    public static function distance(int $meters, string $unit): BigRational
    {
        return Exact::rational($meters)->dividedBy(self::DISTANCE[$unit]);
    }
}
