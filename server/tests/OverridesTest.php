<?php

use Fleetbase\EnhancedPricing\Pricing\PricingFailed;
use Fleetbase\EnhancedPricing\Profile\OverrideValidator;
use Fleetbase\EnhancedPricing\Tests\Support\Orders;
use Fleetbase\EnhancedPricing\Tests\Support\Profiles;

// A customer's overrides: which paths exist, their types, and the profile they make still
// passing every check.

test('valid overrides have no problems', function () {
    $problems = (new OverrideValidator())->check(Profiles::example(), [
        'service_rates.distance.per_meter.rate'       => 160,
        'service_rates.tiered.enabled'                => false,
        'service_rates.tiered.minimum'                => 1000,
        'service_rates.tiered.tiers'                  => [['up_to' => null, 'price' => ['value' => 2000]]],
        'fees.heavy.thresholds'                       => [['over' => 30, 'amount' => 50]],
        'fees.peak.weekdays'                          => [7],
        'fees.peak.window'                            => ['from' => '22:00', 'to' => '05:00'],
        'fees.extra_handling.amount'                  => null,
        'fees.extra_handling.if_missing'              => 'skip',
        'subtotal_fees.percent.percentage'            => 900,
        'subtotal_fees.flat_a.skipped_for'            => [],
        'manual_charges.hourly.offered'               => false,
        'manual_charges.hourly.quantity'              => ['unit' => 'h', 'step' => '0.5', 'maximum' => '12'],
        'manual_charges.entered.entered'              => ['minimum' => 500, 'maximum' => 9000],
        'allowed_service_rates'                       => ['distance'],
    ]);

    expect($problems)->toBe([]);
});

test('an override that the profile cannot take is refused', function (string $path, mixed $value, string $key) {
    $problems = (new OverrideValidator())->check(Profiles::example(), [$path => $value]);

    expect(array_map(static fn ($problem) => [$problem->path, $problem->key], $problems))->toContain([$path, $key]);
})->with([
    'an unknown rule'                  => ['fees.nope.amount', 100, 'enhanced-pricing.profile.unknown-override'],
    'a structure field'                => [
        'fees.heavy.kind',
        'additional',
        'enhanced-pricing.profile.unknown-override',
    ],
    'a charge mode'                    => [
        'subtotal_fees.percent.mode',
        'flat',
        'enhanced-pricing.profile.unknown-override',
    ],
    'how a fee is charged'             => [
        'fees.peak.charge',
        'per_entity',
        'enhanced-pricing.profile.unknown-override',
    ],
    'rule dates'                       => [
        'fees.peak.effective_from',
        '2027-01-01',
        'enhanced-pricing.profile.unknown-override',
    ],
    'the maximum distance'             => [
        'service_rates.distance.per_meter.maximum_distance',
        5000,
        'enhanced-pricing.profile.unknown-override',
    ],
    'a per-meter field on tiers'       => [
        'service_rates.tiered.per_meter.rate',
        100,
        'enhanced-pricing.profile.unknown-override',
    ],
    'a field of another fee kind'      => ['fees.peak.thresholds', [], 'enhanced-pricing.profile.unknown-override'],
    'an unknown holiday list'          => ['holiday_lists.nope', [], 'enhanced-pricing.profile.unknown-override'],
    'money of the wrong type'          => ['fees.extra_handling.amount', '16.00', 'enhanced-pricing.profile.wrong-type'],
    'negative money'                   => ['fees.extra_handling.amount', -1, 'enhanced-pricing.profile.out-of-range'],
    'a percentage as a fraction'       => [
        'subtotal_fees.percent.percentage',
        0.11,
        'enhanced-pricing.profile.wrong-type',
    ],
    'an unknown service rate'          => [
        'allowed_service_rates',
        ['nope'],
        'enhanced-pricing.profile.unknown-reference',
    ],
]);

test('an override that breaks the rule order checks is refused', function (string $path, mixed $value, string $key) {
    $problems = (new OverrideValidator())->check(Profiles::example(), [$path => $value]);

    expect(array_map(static fn ($problem) => $problem->key, $problems))->toContain($key)
        ->and(array_map(static fn ($problem) => $problem->path, $problems))->toContain($path);
})->with([
    'a later rule product'    => [
        'subtotal_fees.flat_a.applies_to',
        ['FLAT_B'],
        'enhanced-pricing.profile.later-product',
    ],
    'its own product'         => ['fees.peak.applies_to', ['PEAK'], 'enhanced-pricing.profile.own-product'],
    'a product in two lists'  => [
        'subtotal_fees.flat_b.skipped_for',
        ['ADDITIONAL'],
        'enhanced-pricing.profile.product-in-two-lists',
    ],
    'an unknown product'      => ['fees.peak.applies_to', ['NOPE'], 'enhanced-pricing.profile.unknown-product'],
    'minimum above maximum'   => [
        'service_rates.distance.per_meter.minimum_distance',
        2000,
        'enhanced-pricing.profile.minimum-above-maximum',
    ],
]);

test('an override cannot change how a manual charge works', function (string $path, mixed $value) {
    $problems = (new OverrideValidator())->check(Profiles::example(), [$path => $value]);

    expect(array_map(static fn ($problem) => [$problem->path, $problem->key], $problems))
        ->toContain([$path, 'enhanced-pricing.profile.changes-charge-mode']);
})->with([
    'an amount on an entered charge'   => ['manual_charges.entered.amount', 100],
    'a flat charge made entered'       => ['manual_charges.hourly.entered', ['minimum' => 100, 'maximum' => 900]],
    'an entered charge made flat'      => ['manual_charges.entered.entered', null],
    'a per-hour charge made flat'      => ['manual_charges.hourly.quantity', null],
    'a quantity on an entered charge'  => [
        'manual_charges.entered.quantity',
        ['unit' => 'h', 'step' => '1', 'maximum' => '5'],
    ],
]);

test('a dated change cannot change how a manual charge works', function () {
    Orders::price(['changes' => [['path' => 'manual_charges.hourly.amount', 'value' => null, 'effective_from' => '2027-01-01']]]);
})->throws(PricingFailed::class, 'enhanced-pricing.pricing.changes-invalid');

test('minimum above maximum is caught across two overrides', function () {
    $problems = (new OverrideValidator())->check(Profiles::example(), [
        'fees.peak.minimum' => 900,
        'fees.peak.maximum' => 800,
    ]);

    expect(array_map(static fn ($problem) => $problem->key, $problems))
        ->toContain('enhanced-pricing.profile.minimum-above-maximum');
});

test('more than 200 overrides are refused', function () {
    $overrides = [];
    for ($index = 0; $index < 201; $index++) {
        $overrides["fees.nope$index.amount"] = 1;
    }

    $problems = (new OverrideValidator())->check(Profiles::example(), $overrides);

    expect($problems[0]->key)->toBe('enhanced-pricing.profile.too-many');
});

test('pricing refuses overrides that do not fit the profile', function () {
    Orders::price(['overrides' => ['fees.nope.amount' => 100]]);
})->throws(PricingFailed::class, 'enhanced-pricing.pricing.overrides-invalid');

test('a tier list override replaces the whole list', function () {
    $profile = Profiles::set(Profiles::example(), 'subtotal_fees.0.enabled', false);

    $quote = Orders::price([
        'profile'   => $profile,
        'overrides' => ['service_rates.tiered.tiers' => [['up_to' => null, 'price' => ['value' => 2000]]]],
        'entities'  => array_fill(0, 3, Orders::entity(5)),
    ]);

    expect(Orders::lines($quote))->toBe([['TIERED', 'Tiered delivery', 3, 2000, 6000]]);
});

test('a holiday list override', function () {
    $profile = Profiles::set(Profiles::example(), 'subtotal_fees.0.enabled', false);
    $profile = Profiles::set($profile, 'holiday_lists', ['main' => []]);
    $profile = Profiles::set($profile, 'fees.2.weekdays', null);
    $profile = Profiles::set($profile, 'fees.2.holidays', 'main');

    $quote = Orders::price(['profile' => $profile, 'overrides' => ['holiday_lists.main' => ['2027-03-03']]]);

    expect($quote->total())->toBe(2700 + 2200);
});
