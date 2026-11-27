<?php

use Fleetbase\EnhancedPricing\Pricing\PricingFailed;
use Fleetbase\EnhancedPricing\Profile\OverrideValidator;
use Fleetbase\EnhancedPricing\Profile\ProfileValidator;
use Fleetbase\EnhancedPricing\Tests\Support\Orders;
use Fleetbase\EnhancedPricing\Tests\Support\Profiles;

// A peak hours or additional fee can charge a percentage of the order's lines for its products,
// once, instead of an amount. Its conditions (the window, the attribute) are the same, and so
// are the minimum and maximum. Fee 2 is peak hours (Saturday and Sunday), fee 0 is additional
// (when the "extra" attribute is on). The service fee is $27.00, so 10% is $2.70.

beforeEach(function () {
    $profile = Profiles::example();
    foreach ([0, 1, 2] as $index) {
        $profile = Profiles::set($profile, "fees.$index.enabled", false);
    }
    foreach ([0, 1, 2] as $index) {
        $profile = Profiles::set($profile, "subtotal_fees.$index.enabled", false);
    }
    $percent = static function (array $profile, int $index, array $fields = []): array {
        $profile = Profiles::set($profile, "fees.$index.enabled", true);
        $profile = Profiles::set($profile, "fees.$index.mode", 'percentage');
        $profile = Profiles::set($profile, "fees.$index.amount", null);
        foreach ($fields + ['percentage' => 1000] as $field => $value) {
            $profile = Profiles::set($profile, "fees.$index.$field", $value);
        }

        return $profile;
    };
    $this->percent = $percent;
    $this->profile = $profile;
    $this->peak    = static fn (array $fields = []) => Orders::price([
        'profile' => $percent($profile, 2, $fields),
        'pickup'  => Orders::SATURDAY,
    ]);
});

test('a peak hours fee as a percentage of the lines for its products', function () {
    $quote = ($this->peak)();

    expect(Orders::lines($quote)[1])->toBe(['PEAK', 'Peak hours fee (10% of 27.00)', 1, 270, 270]);
});

test('a peak hours percentage is left off outside its hours', function () {
    $quote = Orders::price([
        'profile' => ($this->percent)($this->profile, 2),
        'pickup'  => '2027-03-04 10:00',
    ]);

    expect($quote->lines)->toHaveCount(1);
});

test('a percentage fee has a floor and a cap like any fee', function (array $limits, int $cents) {
    $quote = ($this->peak)($limits);

    expect(Orders::lines($quote)[1][4])->toBe($cents);
})->with([
    'cap'   => [['maximum' => 200], 200],
    'floor' => [['minimum' => 500, 'maximum' => 5000], 500],
]);

test('an additional fee as a percentage when its attribute is on', function () {
    $profile = ($this->percent)($this->profile, 0, ['percentage' => 500]);

    $on  = Orders::price(['profile' => $profile, 'attributes' => ['extra' => true]]);
    $off = Orders::price(['profile' => $profile]);

    expect(Orders::lines($on)[1])->toBe(['ADDITIONAL', 'Additional fee (5% of 27.00)', 1, 135, 135])
        ->and($off->lines)->toHaveCount(1);
});

test('a percentage fee with no percentage stops pricing, or is left off when it says skip', function (string $missing) {
    $price = fn () => ($this->peak)(['percentage' => null, 'if_missing' => $missing]);

    if ($missing === 'stop') {
        expect($price)->toThrow(PricingFailed::class, 'enhanced-pricing.pricing.missing-amount');
    } else {
        expect($price()->lines)->toHaveCount(1);
    }
})->with(['stop', 'skip']);

test('a fee without a mode is a flat amount, as before', function () {
    $profile = Profiles::example();
    unset($profile['fees'][2]['mode'], $profile['fees'][2]['percentage']);

    $fee = (new ProfileValidator())->normalize($profile)['fees'][2];

    expect($fee['mode'])->toBe('flat')->and($fee['percentage'])->toBeNull()->and($fee['amount'])->toBe(2200);
});

test('the checks keep the mode and its fields together', function (array $fields, array $expected) {
    $profile = ($this->percent)(Profiles::example(), 2, $fields);

    $problems = array_map(
        static fn ($problem) => [$problem->path, $problem->key],
        (new ProfileValidator())->check($profile),
    );

    expect($problems)->toBe($expected);
})->with([
    'valid'                    => [[], []],
    'an amount in percentage'  => [
        ['amount' => 100],
        [['fees.2.amount', 'enhanced-pricing.profile.not-allowed-for-mode']],
    ],
    'charged per entity'       => [
        ['charge' => 'per_entity'],
        [['fees.2.charge', 'enhanced-pricing.profile.not-one-of']],
    ],
    'a percentage in flat mode' => [
        ['mode' => 'flat', 'amount' => 100],
        [['fees.2.percentage', 'enhanced-pricing.profile.not-allowed-for-mode']],
    ],
]);

test('a percentage can be overridden or changed by date, but not the mode', function () {
    $profile = ($this->percent)(Profiles::example(), 2);
    $checks  = new OverrideValidator();

    $keys = static fn (array $problems): array => array_map(static fn ($problem) => $problem->key, $problems);

    expect($checks->check($profile, ['fees.peak.percentage' => 1500]))->toBe([])
        ->and($keys($checks->check($profile, ['fees.peak.mode' => 'flat'])))
        ->toBe(['enhanced-pricing.profile.unknown-override']);
});
