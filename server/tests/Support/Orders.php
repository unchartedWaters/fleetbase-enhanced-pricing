<?php

namespace Fleetbase\EnhancedPricing\Tests\Support;

use Fleetbase\EnhancedPricing\Pricing\EntityInput;
use Fleetbase\EnhancedPricing\Pricing\ManualChargeInput;
use Fleetbase\EnhancedPricing\Pricing\PricingEngine;
use Fleetbase\EnhancedPricing\Pricing\PricingRequest;
use Fleetbase\EnhancedPricing\Pricing\Quote;

/**
 * Builds pricing requests against the example profile, with the worked examples' rows.
 */
final class Orders
{
    /** Row 1 of the worked examples: $27, $18 and $12, and $9 for the separate entity type. */
    public const ROW_1 = ['code' => 'R1', 'first' => 2700, 'second' => 1800, 'third' => 1200, 'separate_price' => 900];

    /** Row 2: the same with a first price of $27.05. */
    public const ROW_2 = ['code' => 'R2', 'first' => 2705, 'second' => 1800, 'third' => 1200, 'separate_price' => 900];

    /** A Wednesday. */
    public const WEEKDAY = '2027-03-03 10:00';

    /** A Saturday. */
    public const SATURDAY = '2027-03-06 11:00';

    /**
     * @param array<string, mixed> $options profile, overrides, changes, service_rate, row, entities,
     *                                      attributes, pickup (local), pickup_utc, timezone,
     *                                      distance_meters, manual, labels
     */
    public static function request(array $options = []): PricingRequest
    {
        $timezone = is_string($options['timezone'] ?? null) ? $options['timezone'] : 'UTC';
        $local    = is_string($options['pickup'] ?? null) ? $options['pickup'] : self::WEEKDAY;
        $pickup   = new \DateTimeImmutable($local, new \DateTimeZone($timezone));
        if (is_string($options['pickup_utc'] ?? null)) {
            $pickup = new \DateTimeImmutable($options['pickup_utc'], new \DateTimeZone('UTC'));
        }

        /** @var array<string, mixed> $profile */
        $profile = $options['profile'] ?? Profiles::example();
        /** @var list<EntityInput> $entities */
        $entities = $options['entities'] ?? [self::entity(5)];
        /** @var array<string, mixed> $attributes */
        $attributes = $options['attributes'] ?? [];
        /** @var array<string, mixed>|null $row */
        $row = array_key_exists('row', $options) ? $options['row'] : self::ROW_1;
        /** @var list<ManualChargeInput> $manual */
        $manual = $options['manual'] ?? [];
        /** @var array<string, mixed> $overrides */
        $overrides = $options['overrides'] ?? [];
        /** @var list<array{path: string, value: mixed, effective_from: string}> $changes */
        $changes = $options['changes'] ?? [];
        /** @var array<string, string> $labels */
        $labels = $options['labels'] ?? ['customer' => 'Example customer', 'card' => 'Main', 'row' => 'R1'];

        return new PricingRequest(
            profile: $profile,
            serviceRate: is_string($options['service_rate'] ?? null) ? $options['service_rate'] : 'tiered',
            pickupAt: $pickup->setTimezone(new \DateTimeZone('UTC')),
            timezone: $timezone,
            entities: $entities,
            attributes: $attributes,
            row: $row,
            distanceMeters: is_int($options['distance_meters'] ?? null) ? $options['distance_meters'] : null,
            manualCharges: $manual,
            overrides: $overrides,
            valueChanges: $changes,
            labels: $labels,
        );
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function entity(int|string|null $kilograms, ?string $type = null, array $attributes = []): EntityInput
    {
        return new EntityInput(
            type: $type,
            weight: $kilograms,
            weightUnit: $kilograms === null ? null : 'kg',
            attributes: $attributes,
        );
    }

    /**
     * @param array<string, mixed> $options
     */
    public static function price(array $options = []): Quote
    {
        return (new PricingEngine())->price(self::request($options));
    }

    /**
     * The quote's lines as [code, name, quantity, unit amount, amount], for exact comparisons.
     *
     * @return list<array{0: string, 1: string, 2: int, 3: int, 4: int}>
     */
    public static function lines(Quote $quote): array
    {
        $lines = [];
        foreach ($quote->lines as $line) {
            $lines[] = [$line->product, $line->name, $line->quantity, $line->unitAmount, $line->amount()];
        }

        return $lines;
    }
}
