<?php

namespace Fleetbase\EnhancedPricing\Tests\Support;

use Fleetbase\EnhancedPricing\Pricing\EntityInput;

/**
 * Inputs at the documented limits, for the tests that pricing stays correct and fast there.
 */
final class LargeInputs
{
    /**
     * The example profile grown to just under 64 KB as saved, with 50 peak hours fees given only
     * their required fields, so the defaults filled in make it larger than 64 KB.
     *
     * @return array<string, mixed>
     */
    public static function nearSizeLimit(): array
    {
        $profile         = Profiles::example();
        $profile['fees'] = [];
        for ($index = 0; $index < 50; $index++) {
            $profile['fees'][] = [
                'key'        => "w$index",
                'kind'       => 'peak_hours',
                'name'       => "W$index",
                'product'    => 'PEAK',
                'applies_to' => ['TIERED'],
                'charge'     => 'per_order',
                'amount'     => 1,
                'weekdays'   => [6],
            ];
        }
        $profile['holiday_lists'] = array_fill_keys(
            array_map(static fn (int $list): string => "l$list", range(0, 9)),
            [],
        );
        $date                     = new \DateTimeImmutable('2027-01-01');
        for ($count = 0; self::size($profile) < 65000 && $count < 4000; $count++) {
            $profile['holiday_lists']['l' . ($count % 10)][] = $date->format('Y-m-d');
            $date                                            = $date->modify('+1 day');
        }

        return $profile;
    }

    /**
     * The example profile with a number of per-unit parcel fees, each charging every gram.
     *
     * @return array<string, mixed>
     */
    public static function perUnitFees(int $fees): array
    {
        $profile                  = Profiles::example();
        $profile['subtotal_fees'] = [];
        $profile['fees']          = [];
        for ($index = 0; $index < $fees; $index++) {
            $profile['fees'][] = [
                'key'        => "p$index",
                'kind'       => 'parcel',
                'name'       => "P$index",
                'product'    => 'PARCEL',
                'applies_to' => ['TIERED'],
                'charge'     => 'per_unit',
                'basis'      => 'weight',
                'measure'    => 'actual',
                'unit'       => 'g',
                'scope'      => 'entity',
                'thresholds' => [['over' => 0, 'amount' => 1]],
            ];
        }

        return $profile;
    }

    /**
     * Entities weighing 1 g, 2 g and so on, so every per-unit line differs.
     *
     * @return list<EntityInput>
     */
    public static function entities(int $count): array
    {
        return array_map(
            static fn (int $grams): EntityInput => new EntityInput(weight: $grams, weightUnit: 'g'),
            range(1, $count),
        );
    }

    /**
     * @param array<string, mixed> $value
     */
    public static function size(array $value): int
    {
        return strlen((string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
