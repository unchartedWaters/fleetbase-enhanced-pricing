<?php

use Fleetbase\EnhancedPricing\Pricing\PricingFailed;
use Fleetbase\EnhancedPricing\Profile\ProfileValidator;
use Fleetbase\EnhancedPricing\Tests\Support\Orders;
use Fleetbase\EnhancedPricing\Tests\Support\Profiles;

// The distance fee (a mileage premium): the order's distance over a threshold, in the profile's
// distance unit (km in the example profile). Every other fee and subtotal fee is turned off, so
// the tiered service fee for a 5 kg entity ($27.00) is the only other line.

beforeEach(function () {
    $profile = Profiles::example();
    foreach (array_keys($profile['fees']) as $index) {
        $profile = Profiles::set($profile, "fees.$index.enabled", false);
    }
    foreach (array_keys($profile['subtotal_fees']) as $index) {
        $profile = Profiles::set($profile, "subtotal_fees.$index.enabled", false);
    }
    $this->premium = static fn (array $fields): array => Profiles::append($profile, 'fees', $fields + [
        'key'        => 'mileage',
        'kind'       => 'distance',
        'name'       => 'Mileage premium',
        'product'    => 'ENTERED',
        'applies_to' => ['TIERED'],
        'charge'     => 'per_unit',
        'thresholds' => [['over' => '50', 'amount' => 40]],
    ]);
});

test('per unit charges each distance unit over the threshold, as one named line', function () {
    $profile = ($this->premium)([]);

    $quote = Orders::price(['profile' => $profile, 'distance_meters' => 60500]);

    expect(Orders::lines($quote)[1])->toBe(['ENTERED', 'Mileage premium (10.5 km)', 1, 420, 420]);
});

test('bands price each stretch at its own threshold amount', function () {
    $bands   = [['over' => '50', 'amount' => 40], ['over' => '100', 'amount' => 60]];
    $profile = ($this->premium)(['thresholds' => $bands]);

    $quote = Orders::price(['profile' => $profile, 'distance_meters' => 120000]);

    expect(Orders::lines($quote)[1][4])->toBe(50 * 40 + 20 * 60);
});

test('per order charges the highest threshold passed, once', function () {
    $profile = ($this->premium)(['charge' => 'per_order', 'thresholds' => [['over' => '50', 'amount' => 2500]]]);

    expect(Orders::lines(Orders::price(['profile' => $profile, 'distance_meters' => 50001]))[1][4])->toBe(2500)
        ->and(Orders::price(['profile' => $profile, 'distance_meters' => 50000])->total())->toBe(2700);
});

test('a distance at or under the first threshold makes no line', function () {
    $quote = Orders::price(['profile' => ($this->premium)([]), 'distance_meters' => 50000]);

    expect($quote->total())->toBe(2700);
});

test('the fee takes its minimum and maximum like every fee', function () {
    $profile = ($this->premium)(['maximum' => 100]);

    $quote = Orders::price(['profile' => $profile, 'distance_meters' => 80000]);

    expect(Orders::lines($quote)[1])->toBe(['ENTERED', 'Mileage premium', 1, 100, 100]);
});

test('an order without a distance stops with a message naming the fee', function () {
    Orders::price(['profile' => ($this->premium)([])]);
})->throws(PricingFailed::class, 'enhanced-pricing.pricing.distance-required');

test('the profile checks a distance fee', function () {
    $profile = ($this->premium)(['charge' => 'per_entity', 'thresholds' => []]);

    $paths = array_map(static fn ($problem) => $problem->path, (new ProfileValidator())->check($profile));

    expect($paths)->toContain('fees.' . (count($profile['fees']) - 1) . '.thresholds');
});
