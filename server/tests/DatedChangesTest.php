<?php

use Fleetbase\EnhancedPricing\Pricing\PricingFailed;
use Fleetbase\EnhancedPricing\Profile\ChangeValidator;
use Fleetbase\EnhancedPricing\Tests\Support\Orders;
use Fleetbase\EnhancedPricing\Tests\Support\Profiles;

// Dated changes to global values: which values can change, which change is in effect on a date,
// and how they meet a customer's overrides.

test('money, percent and number values can change by date', function () {
    $problems = (new ChangeValidator())->check(Profiles::example(), [
        ['path' => 'subtotal_fees.percent.percentage', 'value' => 1300, 'effective_from' => '2027-04-01'],
        ['path' => 'fees.additional.amount', 'value' => 1700, 'effective_from' => '2027-04-01'],
        ['path' => 'fees.parcel.thresholds', 'value' => [['over' => 30, 'amount' => 45]], 'effective_from' => '2027-04-01'],
        ['path' => 'service_rates.distance.per_meter.rate', 'value' => 220, 'effective_from' => '2027-04-01'],
        ['path' => 'service_rates.distance.per_meter.minimum_distance', 'value' => '12.5', 'effective_from' => '2027-04-01'],
        ['path' => 'service_rates.tiered.tiers', 'value' => [['up_to' => null, 'price' => ['value' => 2000]]], 'effective_from' => '2027-04-01'],
        ['path' => 'manual_charges.hourly.amount', 'value' => 4500, 'effective_from' => '2027-04-01'],
    ]);

    expect($problems)->toBe([]);
});

test('other values cannot change by date', function (array $change, string $key) {
    $problems = (new ChangeValidator())->check(Profiles::example(), [$change]);

    expect($problems[0]->key)->toBe($key)->and($problems[0]->path)->toStartWith('0');
})->with([
    'a switch'        => [['path' => 'fees.peak.enabled', 'value' => false, 'effective_from' => '2027-04-01'], 'enhanced-pricing.profile.not-dated'],
    'a product list'  => [['path' => 'fees.peak.applies_to', 'value' => ['TIERED'], 'effective_from' => '2027-04-01'], 'enhanced-pricing.profile.not-dated'],
    'the rounding'    => [['path' => 'service_rates.distance.per_meter.rounding', 'value' => 'none', 'effective_from' => '2027-04-01'], 'enhanced-pricing.profile.not-dated'],
    'allowed rates'   => [['path' => 'allowed_service_rates', 'value' => [], 'effective_from' => '2027-04-01'], 'enhanced-pricing.profile.not-dated'],
    'an unknown path' => [['path' => 'fees.nope.amount', 'value' => 1, 'effective_from' => '2027-04-01'], 'enhanced-pricing.profile.unknown-override'],
    'a bad date'      => [['path' => 'fees.peak.amount', 'value' => 1, 'effective_from' => '2027-02-30'], 'enhanced-pricing.profile.wrong-type'],
    'a bad value'     => [['path' => 'fees.peak.amount', 'value' => 'a lot', 'effective_from' => '2027-04-01'], 'enhanced-pricing.profile.wrong-type'],
    'a missing field' => [['path' => 'fees.peak.amount', 'value' => 1], 'enhanced-pricing.profile.missing-field'],
]);

test('the latest change on or before the pricing date wins', function (string $pickup, int $total) {
    $profile = Profiles::set(Profiles::example(), 'fees.2.weekdays', [3]);
    foreach ([0, 1, 2] as $index) {
        $profile = Profiles::set($profile, "subtotal_fees.$index.enabled", false);
    }

    $quote = Orders::price(['profile' => $profile, 'pickup' => $pickup, 'changes' => [
        ['path' => 'fees.peak.amount', 'value' => 3000, 'effective_from' => '2027-05-05'],
        ['path' => 'fees.peak.amount', 'value' => 2500, 'effective_from' => '2027-04-07'],
    ]]);

    expect($quote->total())->toBe($total);
})->with([
    'before both'  => ['2027-03-31 10:00', 2700 + 2200],
    'after one'    => ['2027-04-07 10:00', 2700 + 2500],
    'after both'   => ['2027-05-05 10:00', 2700 + 3000],
]);

test('a change takes effect at 00:00 in the pickup timezone', function (string $utc, int $total) {
    $quote = Orders::price([
        'pickup_utc' => $utc,
        'timezone'   => 'America/Edmonton',
        'changes'    => [['path' => 'subtotal_fees.percent.percentage', 'value' => 1300, 'effective_from' => '2027-04-01']],
    ]);

    expect($quote->total())->toBe($total);
})->with([
    '23:30 on March 31 in Edmonton, April 1 in UTC' => ['2027-04-01 05:30', 2997],
    '00:30 on April 1 in Edmonton'                  => ['2027-04-01 06:30', 3051],
]);

test('a change and an override that together break a check stop pricing', function () {
    Orders::price([
        'overrides' => ['subtotal_fees.percent.minimum' => 800],
        'changes'   => [['path' => 'subtotal_fees.percent.maximum', 'value' => 500, 'effective_from' => '2027-01-01']],
    ]);
})->throws(PricingFailed::class, 'enhanced-pricing.pricing.profile-invalid-for-order');

test('pricing refuses changes that do not fit the profile', function () {
    Orders::price(['changes' => [['path' => 'fees.peak.enabled', 'value' => false, 'effective_from' => '2027-01-01']]]);
})->throws(PricingFailed::class, 'enhanced-pricing.pricing.changes-invalid');

test('the quote records the values that came from overrides and changes', function () {
    $quote = Orders::price([
        'pickup'    => '2027-04-02 10:00',
        'overrides' => ['fees.additional.amount' => 1000],
        'changes'   => [
            ['path' => 'subtotal_fees.percent.percentage', 'value' => 1300, 'effective_from' => '2027-04-01'],
            ['path' => 'subtotal_fees.percent.percentage', 'value' => 1500, 'effective_from' => '2027-06-01'],
        ],
    ]);

    expect($quote->meta)->toMatchArray([
        'timezone'     => 'UTC',
        'pricing_date' => '2027-04-02',
        'service_rate' => 'tiered',
        'overrides'    => ['fees.additional.amount' => 1000],
        'changes'      => ['subtotal_fees.percent.percentage' => '2027-04-01'],
    ]);
});

test('two changes to one value on the same date are refused', function () {
    $problems = (new ChangeValidator())->check(Profiles::example(), [
        ['path' => 'fees.peak.amount', 'value' => 2300, 'effective_from' => '2027-04-01'],
        ['path' => 'fees.peak.amount', 'value' => 2400, 'effective_from' => '2027-04-01'],
    ]);

    expect(array_map(static fn ($problem) => [$problem->path, $problem->key], $problems))
        ->toBe([['1.effective_from', 'enhanced-pricing.profile.duplicate']]);
});
