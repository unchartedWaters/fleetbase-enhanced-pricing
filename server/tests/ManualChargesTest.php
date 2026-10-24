<?php

use Fleetbase\EnhancedPricing\Pricing\ManualChargeInput;
use Fleetbase\EnhancedPricing\Pricing\PricingFailed;
use Fleetbase\EnhancedPricing\Tests\Support\Orders;
use Fleetbase\EnhancedPricing\Tests\Support\Profiles;

// Manual charges: flat, per quantity and entered, with their limits, offers, dates and stored
// amounts. Subtotal fees are turned off.

beforeEach(function () {
    $profile = Profiles::example();
    foreach ([0, 1, 2] as $index) {
        $profile = Profiles::set($profile, "subtotal_fees.$index.enabled", false);
    }
    $this->profile = $profile;
});

test('a flat manual charge without a quantity', function () {
    $profile = Profiles::set($this->profile, 'manual_charges.0.quantity', null);

    $quote = Orders::price(['profile' => $profile, 'manual' => [new ManualChargeInput(key: 'hourly')]]);

    expect(Orders::lines($quote)[1])->toBe(['HOURLY', 'Hourly charge', 1, 4200, 4200]);
});

test('a quantity must be a positive multiple of its step, up to its maximum', function (mixed $quantity) {
    Orders::price(['profile' => $this->profile, 'manual' => [new ManualChargeInput(key: 'hourly', quantity: $quantity)]]);
})->throws(PricingFailed::class, 'enhanced-pricing.pricing.manual-charge-quantity-invalid')->with([
    'not a step'   => ['1.1'],
    'zero'         => ['0'],
    'negative'     => ['-1'],
    'above'        => ['24.25'],
    'missing'      => [null],
    'not a number' => ['two'],
]);

test('the quantity maximum is allowed', function () {
    $quote = Orders::price(['profile' => $this->profile, 'manual' => [new ManualChargeInput(key: 'hourly', quantity: 24)]]);

    expect(Orders::lines($quote)[1])->toBe(['HOURLY', 'Hourly charge (24 h)', 1, 100800, 100800]);
});

test('a charge without a quantity refuses one, and a flat charge refuses an amount', function (string $key, mixed $quantity, ?int $amount, string $error) {
    expect(fn () => Orders::price(['profile' => $this->profile, 'manual' => [new ManualChargeInput(key: $key, quantity: $quantity, amount: $amount)]]))
        ->toThrow(PricingFailed::class, $error);
})->with([
    'entered with a quantity' => ['entered', '1', 1000, 'enhanced-pricing.pricing.manual-charge-quantity-invalid'],
    'entered with no amount'  => ['entered', null, null, 'enhanced-pricing.pricing.manual-charge-amount-out-of-range'],
    'entered below minimum'   => ['entered', null, 799, 'enhanced-pricing.pricing.manual-charge-amount-out-of-range'],
    'flat with an amount'     => ['hourly', '1', 1000, 'enhanced-pricing.pricing.manual-charge-amount-not-entered'],
]);

test('a charge that is not offered is refused, unless an override offers it', function () {
    $profile = Profiles::set($this->profile, 'manual_charges.1.offered', false);
    $charge  = new ManualChargeInput(key: 'entered', amount: 1000);

    expect(fn () => Orders::price(['profile' => $profile, 'manual' => [$charge]]))
        ->toThrow(PricingFailed::class, 'enhanced-pricing.pricing.manual-charge-not-offered');

    expect(Orders::price(['profile' => $profile, 'manual' => [$charge], 'overrides' => ['manual_charges.entered.offered' => true]])->total())->toBe(3700);
});

test('a charge outside its dates is refused when added', function () {
    $profile = Profiles::set($this->profile, 'manual_charges.0.effective_until', '2027-03-02');

    Orders::price(['profile' => $profile, 'manual' => [new ManualChargeInput(key: 'hourly', quantity: '1')]]);
})->throws(PricingFailed::class, 'enhanced-pricing.pricing.manual-charge-not-in-effect');

test('a stored charge keeps its amount whatever the profile now says', function () {
    $profile = Profiles::set($this->profile, 'manual_charges.0.amount', 5000);
    $profile = Profiles::set($profile, 'manual_charges.0.offered', false);
    $profile = Profiles::set($profile, 'manual_charges.0.effective_until', '2020-01-01');

    $quote = Orders::price(['profile' => $profile, 'manual' => [new ManualChargeInput(key: 'hourly', quantity: '1.75', amount: 7350, stored: true)]]);

    expect(Orders::lines($quote)[1])->toBe(['HOURLY', 'Hourly charge (1.75 h)', 1, 7350, 7350]);
});

test('a stored charge whose key the profile no longer has stops pricing', function () {
    $profile = Profiles::remove($this->profile, 'manual_charges.0');

    Orders::price(['profile' => $profile, 'manual' => [new ManualChargeInput(key: 'hourly', quantity: '1', amount: 4200, stored: true)]]);
})->throws(PricingFailed::class, 'enhanced-pricing.pricing.unknown-manual-charge');

test('a stored charge needs its amount', function () {
    Orders::price(['profile' => $this->profile, 'manual' => [new ManualChargeInput(key: 'hourly', quantity: '1', stored: true)]]);
})->throws(PricingFailed::class, 'enhanced-pricing.pricing.manual-charge-amount-out-of-range');

test('the same charge can be on an order twice', function () {
    $charges = [new ManualChargeInput(key: 'entered', amount: 1000), new ManualChargeInput(key: 'entered', amount: 1000)];

    expect(Orders::lines(Orders::price(['profile' => $this->profile, 'manual' => $charges]))[1])->toBe(['ENTERED', 'Entered charge', 2, 1000, 2000]);
});

test('more than 20 manual charges on an order are refused', function () {
    Orders::price(['profile' => $this->profile, 'manual' => array_fill(0, 21, new ManualChargeInput(key: 'entered', amount: 1000))]);
})->throws(PricingFailed::class, 'enhanced-pricing.pricing.too-many-manual-charges');

test('a manual charge line does not count for a per-entity fee', function () {
    $profile = Profiles::set($this->profile, 'fees.2.charge', 'per_entity');
    $profile = Profiles::set($profile, 'fees.2.applies_to', ['HOURLY']);

    $quote = Orders::price(['profile' => $profile, 'pickup' => Orders::SATURDAY, 'manual' => [new ManualChargeInput(key: 'hourly', quantity: '1')]]);

    expect($quote->total())->toBe(2700 + 4200);
});
