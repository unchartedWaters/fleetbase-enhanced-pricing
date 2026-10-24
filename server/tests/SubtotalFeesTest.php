<?php

use Fleetbase\EnhancedPricing\Pricing\ManualChargeInput;
use Fleetbase\EnhancedPricing\Pricing\PricingFailed;
use Fleetbase\EnhancedPricing\Tests\Support\Orders;
use Fleetbase\EnhancedPricing\Tests\Support\Profiles;

// Subtotal fees, percentage and flat. Subtotal fees 0, 1 and 2 of the example profile are the
// percentage one and flat fees A and B. Every fee and subtotal fee starts turned off.

beforeEach(function () {
    $profile = Profiles::example();
    foreach ([0, 1, 2] as $index) {
        $profile = Profiles::set($profile, "subtotal_fees.$index.enabled", false);
        $profile = Profiles::set($profile, "fees.$index.enabled", false);
    }
    $this->profile = $profile;
    $this->with    = static function (array $profile, int $index, array $fields): array {
        $profile = Profiles::set($profile, "subtotal_fees.$index.enabled", true);
        foreach ($fields as $field => $value) {
            $profile = Profiles::set($profile, "subtotal_fees.$index.$field", $value);
        }

        return $profile;
    };
});

test('a percentage of the lines for its chosen products only', function () {
    $profile = ($this->with)($this->profile, 0, ['applies_to' => ['SEPARATE']]);

    $quote = Orders::price(['profile' => $profile, 'entities' => [Orders::entity(5), Orders::entity(5, 'separate')]]);

    // 11% of $9 is $0.99.
    expect(Orders::lines($quote)[2])->toBe(['PERCENT', 'Percentage subtotal fee', 1, 99, 99]);
});

test('requires and skipped for', function (array $fields, bool $charged) {
    $profile = ($this->with)($this->profile, 1, $fields + ['applies_to' => ['TIERED'], 'skipped_for' => []]);

    $quote = Orders::price(['profile' => $profile, 'entities' => [Orders::entity(5), Orders::entity(5, 'separate')]]);

    expect($quote->total())->toBe(2700 + 900 + ($charged ? 400 : 0));
})->with([
    'requires a product on the order'     => [['requires' => ['SEPARATE']], true],
    'requires a product not on the order' => [['requires' => ['DISTANCE']], false],
    'skipped for a product on the order'  => [['skipped_for' => ['SEPARATE']], false],
    'skipped for another product'         => [['skipped_for' => ['DISTANCE']], true],
]);

test('a flat subtotal fee per line counts the combined lines', function () {
    $profile = ($this->with)($this->profile, 1, [
        'applies_to'  => ['TIERED'],
        'skipped_for' => [],
        'charge'      => 'per_line',
    ]);

    $quote = Orders::price(['profile' => $profile, 'entities' => array_fill(0, 6, Orders::entity(5))]);

    expect(array_slice(Orders::lines($quote), 3))->toBe([['FLAT_A', 'Flat subtotal fee A', 3, 400, 1200]]);
});

test('a flat subtotal fee per entity counts entities on its chosen products', function () {
    $profile = ($this->with)($this->profile, 1, [
        'applies_to'  => ['TIERED'],
        'skipped_for' => [],
        'charge'      => 'per_entity',
    ]);

    $quote = Orders::price([
        'profile'  => $profile,
        'entities' => [Orders::entity(5), Orders::entity(5), Orders::entity(5, 'separate')],
    ]);

    expect(array_slice(Orders::lines($quote), 2))->toBe([['FLAT_A', 'Flat subtotal fee A', 2, 400, 800]]);
});

test('a manual charge can make a flat subtotal fee apply', function () {
    $profile = ($this->with)($this->profile, 1, ['applies_to' => ['HOURLY'], 'skipped_for' => []]);

    $quote = Orders::price([
        'profile' => $profile,
        'manual'  => [new ManualChargeInput(key: 'hourly', quantity: '0.5')],
    ]);

    expect(Orders::lines($quote)[2])->toBe(['FLAT_A', 'Flat subtotal fee A', 1, 400, 400]);
});

test('a later subtotal fee sees an earlier one', function () {
    $profile = ($this->with)($this->profile, 0, []);
    $profile = ($this->with)($profile, 1, [
        'mode'        => 'percentage',
        'percentage'  => 1000,
        'amount'      => null,
        'applies_to'  => ['PERCENT'],
        'skipped_for' => [],
    ]);

    $quote = Orders::price(['profile' => $profile]);

    // 11% of $27 is $2.97, and 10% of that is $0.297, rounded to $0.30.
    expect(array_slice(Orders::lines($quote), 1))->toBe([
        ['PERCENT', 'Percentage subtotal fee', 1, 297, 297],
        ['FLAT_A', 'Flat subtotal fee A', 1, 30, 30],
    ]);
});

test('a missing percentage stops pricing, or is skipped', function () {
    $profile = ($this->with)($this->profile, 0, ['percentage' => null]);

    expect(fn () => Orders::price(['profile' => $profile]))
        ->toThrow(PricingFailed::class, 'enhanced-pricing.pricing.missing-amount');

    $skipping = Profiles::set($profile, 'subtotal_fees.0.if_missing', 'skip');

    expect(Orders::price(['profile' => $skipping])->total())->toBe(2700);
});

test('a customer override sets a missing percentage', function () {
    $profile = ($this->with)($this->profile, 0, ['percentage' => null]);

    expect(Orders::price(['profile' => $profile, 'overrides' => ['subtotal_fees.percent.percentage' => 1000]])->total())
        ->toBe(2970);
});

test('minimum and maximum on a subtotal fee', function (array $fields, int $amount) {
    $profile = ($this->with)($this->profile, 0, $fields);

    expect(Orders::lines(Orders::price(['profile' => $profile]))[1])
        ->toBe(['PERCENT', 'Percentage subtotal fee', 1, $amount, $amount]);
})->with([
    'minimum' => [['minimum' => 500], 500],
    'maximum' => [['maximum' => 200], 200],
]);

test('subtotal fee conditions can be overridden for one customer', function () {
    $profile = ($this->with)($this->profile, 1, ['applies_to' => ['TIERED'], 'skipped_for' => []]);

    $quote = Orders::price(['profile' => $profile, 'overrides' => ['subtotal_fees.flat_a.requires' => ['DISTANCE']]]);

    expect($quote->total())->toBe(2700);
});
