<?php

use Fleetbase\EnhancedPricing\Tests\Support\Orders;
use Fleetbase\EnhancedPricing\Tests\Support\Profiles;

// A percentage fee with a cap ("10% up to $100") or a floor and a cap ("$10 minimum, 5%, up to
// $50"). The percentage is worked out exactly and rounded once, half up, to the minor unit; the
// minimum and maximum then apply to that amount. Only the tiered service fee ($27.00 for a 5 kg
// entity) and the percent subtotal fee are on.

beforeEach(function () {
    $profile = Profiles::example();
    foreach ([0, 1, 2] as $index) {
        $profile = Profiles::set($profile, "fees.$index.enabled", false);
    }
    foreach ([1, 2] as $index) {
        $profile = Profiles::set($profile, "subtotal_fees.$index.enabled", false);
    }
    $this->percent = static function (array $fields) use ($profile): array {
        foreach ($fields + ['percentage' => 750] as $field => $value) {
            $profile = Profiles::set($profile, "subtotal_fees.0.$field", $value);
        }

        return $profile;
    };
});

test('the percentage is rounded once, half up, to the minor unit', function () {
    $quote = Orders::price(['profile' => ($this->percent)([])]);

    expect(Orders::lines($quote)[1])->toBe(['PERCENT', 'Percentage subtotal fee (7.5% of 27.00)', 1, 203, 203]);
});

test('a cap replaces a larger amount with one line at the cap', function () {
    $quote = Orders::price(['profile' => ($this->percent)(['maximum' => 150])]);

    expect(Orders::lines($quote)[1])->toBe(['PERCENT', 'Percentage subtotal fee', 1, 150, 150]);
});

test('a floor replaces a smaller amount with one line at the floor', function () {
    $quote = Orders::price(['profile' => ($this->percent)(['minimum' => 500, 'maximum' => 5000])]);

    expect(Orders::lines($quote)[1])->toBe(['PERCENT', 'Percentage subtotal fee', 1, 500, 500]);
});

test('an amount between the floor and the cap is left as it is', function () {
    $quote = Orders::price(['profile' => ($this->percent)(['minimum' => 100, 'maximum' => 5000])]);

    expect(Orders::lines($quote)[1])->toBe(['PERCENT', 'Percentage subtotal fee (7.5% of 27.00)', 1, 203, 203]);
});

test('the limits apply to the rounded amount: 202.5 rounds up to 203 and a cap of 202 wins', function () {
    $quote = Orders::price(['profile' => ($this->percent)(['maximum' => 202])]);

    expect(Orders::lines($quote)[1][4])->toBe(202);
});

test('a percentage of $0 makes no line, whatever the floor', function () {
    $quote = Orders::price(['profile' => ($this->percent)(['percentage' => 0, 'minimum' => 500])]);

    expect($quote->lines)->toHaveCount(1);
});
