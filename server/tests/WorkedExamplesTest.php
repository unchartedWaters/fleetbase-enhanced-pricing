<?php

use Fleetbase\EnhancedPricing\Pricing\ManualChargeInput;
use Fleetbase\EnhancedPricing\Pricing\PricingFailed;
use Fleetbase\EnhancedPricing\Tests\Support\Orders;

// The worked examples from the plan, priced on the example profile in server/tests/Fixtures.
// Every pickup in them is on UTC.

test('a tiered order with every fee and subtotal fee', function () {
    $quote = Orders::price([
        'pickup'     => Orders::SATURDAY,
        'attributes' => ['extra' => true],
        'entities'   => [
            Orders::entity(36),
            Orders::entity(5),
            Orders::entity(5),
            Orders::entity(5),
            Orders::entity(5),
            Orders::entity(5),
            Orders::entity(5, 'separate'),
        ],
    ]);

    expect(Orders::lines($quote))->toBe([
        ['TIERED', 'Tiered delivery', 2, 2700, 5400],
        ['TIERED', 'Tiered delivery', 3, 1800, 5400],
        ['TIERED', 'Tiered delivery', 1, 1200, 1200],
        ['SEPARATE', 'Separate entity type', 1, 900, 900],
        ['ADDITIONAL', 'Additional fee', 1, 1600, 1600],
        ['PARCEL', 'Parcel fee (6 kg)', 1, 240, 240],
        ['PEAK', 'Peak hours fee', 1, 2200, 2200],
        ['PERCENT', 'Percentage subtotal fee', 1, 1863, 1863],
        ['FLAT_A', 'Flat subtotal fee A', 1, 400, 400],
        ['FLAT_B', 'Flat subtotal fee B', 1, 600, 600],
    ])->and($quote->total())->toBe(19803);
});

test('a distance order raised to the minimum distance', function () {
    $quote = Orders::price([
        'service_rate'    => 'distance',
        'row'             => null,
        'distance_meters' => 7000,
        'entities'        => [Orders::entity(42)],
    ]);

    expect(Orders::lines($quote))->toBe([
        ['DISTANCE', 'Distance (10 km)', 1, 3500, 3500],
        ['PARCEL', 'Parcel fee (12 kg)', 1, 480, 480],
        ['PERCENT', 'Percentage subtotal fee', 1, 53, 53],
    ])->and($quote->total())->toBe(4033);
});

test('a percentage is rounded half up to the cent', function () {
    $quote = Orders::price(['row' => Orders::ROW_2]);

    expect(Orders::lines($quote))->toBe([
        ['TIERED', 'Tiered delivery', 1, 2705, 2705],
        ['PERCENT', 'Percentage subtotal fee', 1, 298, 298],
    ])->and($quote->total())->toBe(3003);
});

test('a customer override replaces one global value', function () {
    $quote = Orders::price([
        'service_rate'    => 'distance',
        'row'             => null,
        'distance_meters' => 15000,
        'overrides'       => ['service_rates.distance.per_meter.rate' => 160],
    ]);

    expect(Orders::lines($quote))->toBe([
        ['DISTANCE', 'Distance (15 km)', 1, 3800, 3800],
    ])->and($quote->total())->toBe(3800);
});

test('an hourly manual charge', function () {
    $quote = Orders::price(['manual' => [ManualChargeInput::new('hourly', quantity: '1.75')]]);

    expect(Orders::lines($quote))->toBe([
        ['TIERED', 'Tiered delivery', 1, 2700, 2700],
        ['HOURLY', 'Hourly charge (1.75 h)', 1, 7350, 7350],
        ['PERCENT', 'Percentage subtotal fee', 1, 297, 297],
    ])->and($quote->total())->toBe(10347);
});

test('an entered manual charge within its limits', function () {
    $quote = Orders::price(['manual' => [ManualChargeInput::new('entered', amount: 6400)]]);

    expect(Orders::lines($quote))->toBe([
        ['TIERED', 'Tiered delivery', 1, 2700, 2700],
        ['ENTERED', 'Entered charge', 1, 6400, 6400],
        ['PERCENT', 'Percentage subtotal fee', 1, 297, 297],
    ])->and($quote->total())->toBe(9397);
});

test('an entered manual charge above its maximum is refused', function () {
    Orders::price(['manual' => [ManualChargeInput::new('entered', amount: 16000)]]);
})->throws(PricingFailed::class, 'enhanced-pricing.pricing.manual-charge-amount-out-of-range');

test('a scheduled change takes effect at 00:00 on its date', function (string $pickup, array $overrides, int $total) {
    $quote = Orders::price([
        'pickup'    => $pickup,
        'overrides' => $overrides,
        'changes'   => [[
            'path'           => 'subtotal_fees.percent.percentage',
            'value'          => 1300,
            'effective_from' => '2027-04-01',
        ]],
    ]);

    expect($quote->total())->toBe($total);
})->with([
    'the day before'                  => ['2027-03-31 12:00', [], 2997],
    'at 00:00 on the date'            => ['2027-04-01 00:00', [], 3051],
    'an override, before the date'    => ['2027-03-31 12:00', ['subtotal_fees.percent.percentage' => 900], 2943],
    'an override, on the date'        => ['2027-04-01 00:00', ['subtotal_fees.percent.percentage' => 900], 2943],
]);
