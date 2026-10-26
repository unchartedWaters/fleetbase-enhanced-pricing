<?php

use Fleetbase\EnhancedPricing\Pricing\EntityInput;
use Fleetbase\EnhancedPricing\Pricing\ManualChargeInput;
use Fleetbase\EnhancedPricing\Pricing\PricingFailed;
use Fleetbase\EnhancedPricing\Tests\Support\Orders;
use Fleetbase\EnhancedPricing\Tests\Support\Profiles;

// Peak hours, parcel and additional fees, each on its own: every other fee and every subtotal
// fee is turned off. Fees 0, 1 and 2 of the example profile are additional, parcel and peak.

beforeEach(function () {
    $profile = Profiles::example();
    foreach ([0, 1, 2] as $index) {
        $profile = Profiles::set($profile, "subtotal_fees.$index.enabled", false);
        $profile = Profiles::set($profile, "fees.$index.enabled", false);
    }
    $this->profile = $profile;
    $this->fee     = static function (array $profile, int $index, array $fields): array {
        $profile = Profiles::set($profile, "fees.$index.enabled", true);
        foreach ($fields as $field => $value) {
            $profile = Profiles::set($profile, "fees.$index.$field", $value);
        }

        return $profile;
    };
});

test('a peak hours window crossing 00:00', function (string $pickup, bool $charged) {
    $profile = ($this->fee)($this->profile, 2, ['window' => ['from' => '21:00', 'to' => '06:00'], 'weekdays' => null]);

    $quote = Orders::price(['profile' => $profile, 'pickup' => $pickup]);

    expect($quote->total())->toBe($charged ? 2700 + 2200 : 2700);
})->with([
    'before the window' => ['2027-03-03 20:59', false],
    'at its start'      => ['2027-03-03 21:00', true],
    'after 00:00'       => ['2027-03-04 05:59', true],
    'at its end'        => ['2027-03-04 06:00', false],
]);

test(
    'a peak hours fee on weekdays, holidays, or both with a window',
    function (array $fields, string $pickup, bool $charged) {
        $profile = Profiles::set($this->profile, 'holiday_lists', ['main' => ['2027-03-03']]);
        $profile = ($this->fee)($profile, 2, $fields);

        $quote = Orders::price(['profile' => $profile, 'pickup' => $pickup]);

        expect($quote->total())->toBe($charged ? 4900 : 2700);
    },
)->with([
    'weekday matches'               => [['weekdays' => [6, 7]], Orders::SATURDAY, true],
    'weekday does not match'        => [['weekdays' => [6, 7]], '2027-03-04 10:00', false],
    'holiday matches'               => [['weekdays' => null, 'holidays' => 'main'], '2027-03-03 10:00', true],
    'not a holiday'                 => [['weekdays' => null, 'holidays' => 'main'], '2027-03-04 10:00', false],
    'weekday or holiday, holiday'   => [['weekdays' => [6], 'holidays' => 'main'], '2027-03-03 10:00', true],
    'window and weekday, both'      => [
        ['weekdays' => [6], 'window' => ['from' => '09:00', 'to' => '12:00']],
        Orders::SATURDAY,
        true,
    ],
    'window and weekday, no window' => [
        ['weekdays' => [6], 'window' => ['from' => '12:00', 'to' => '18:00']],
        Orders::SATURDAY,
        false,
    ],
]);

test('peak hours are judged in the pickup timezone, across daylight saving', function (string $utc, bool $charged) {
    $profile = ($this->fee)($this->profile, 2, ['window' => ['from' => '21:00', 'to' => '06:00'], 'weekdays' => null]);

    $quote = Orders::price(['profile' => $profile, 'pickup_utc' => $utc, 'timezone' => 'America/New_York']);

    expect($quote->total())->toBe($charged ? 4900 : 2700);
})->with([
    'standard time, 20:30 local'  => ['2027-03-02 01:30', false],
    'standard time, 21:30 local'  => ['2027-03-02 02:30', true],
    'daylight time, 21:30 local'  => ['2027-03-16 01:30', true],
    'daylight time, 20:30 local'  => ['2027-03-16 00:30', false],
]);

test('the weekday is the pickup local date', function () {
    $profile = ($this->fee)($this->profile, 2, ['weekdays' => [6]]);

    // Saturday 23:30 in Edmonton is Sunday 06:30 UTC.
    $quote = Orders::price(['profile' => $profile, 'pickup' => '2027-03-06 23:30', 'timezone' => 'America/Edmonton']);

    expect($quote->total())->toBe(4900);
});

test('a fee charged per entity is one line with a quantity', function () {
    $profile = ($this->fee)($this->profile, 2, ['charge' => 'per_entity']);

    $quote = Orders::price([
        'profile'  => $profile,
        'pickup'   => Orders::SATURDAY,
        'entities' => array_fill(0, 3, Orders::entity(5)),
    ]);

    expect(Orders::lines($quote)[2])->toBe(['PEAK', 'Peak hours fee', 3, 2200, 6600]);
});

test('parcel fee thresholds per entity, grouped by amount', function () {
    $profile = ($this->fee)($this->profile, 1, [
        'charge'     => 'per_entity',
        'thresholds' => [['over' => 20, 'amount' => 500], ['over' => 30, 'amount' => 900]],
    ]);

    $quote = Orders::price([
        'profile'  => $profile,
        'entities' => [
            Orders::entity(25),
            Orders::entity(35),
            Orders::entity(35),
            Orders::entity(10),
            Orders::entity(20),
        ],
    ]);

    expect(array_slice(Orders::lines($quote), 2))->toBe([
        ['PARCEL', 'Parcel fee', 1, 500, 500],
        ['PARCEL', 'Parcel fee', 2, 900, 1800],
    ]);
});

test('parcel fee per unit in other weight units', function () {
    $profile = ($this->fee)($this->profile, 1, []);
    $entity  = new EntityInput(weight: 70, weightUnit: 'lb');

    $quote = Orders::price(['profile' => $profile, 'entities' => [$entity]]);

    // 70 lb is 31.7514659 kg: 1.7514659 kg over, at $0.40 a kg, is 70.06 cents.
    expect(Orders::lines($quote)[1])->toBe(['PARCEL', 'Parcel fee (1.751 kg)', 1, 70, 70]);
});

test(
    'parcel fee on dimensional weight, or the greater of the two',
    function (string $measure, int $kilograms, bool $charged) {
        $profile = ($this->fee)($this->profile, 1, [
            'charge'     => 'per_entity',
            'measure'    => $measure,
            'divisor'    => $measure === 'actual' ? null : 5000,
            'thresholds' => [['over' => 10, 'amount' => 500]],
        ]);
        // 50 × 40 × 30 cm is 60,000 cm³, 12 kg at 5000 cm³ per kg.
        $entity = new EntityInput(
            weight: $kilograms,
            weightUnit: 'kg',
            length: 50,
            width: 40,
            height: 30,
            dimensionsUnit: 'cm',
        );

        $quote = Orders::price(['profile' => $profile, 'entities' => [$entity]]);

        expect($quote->total())->toBe($charged ? 3200 : 2700);
    },
)->with([
    'dimensional over'      => ['dimensional', 5, true],
    'actual, light'         => ['actual', 5, false],
    'greater, dimensional'  => ['greater', 5, true],
    'greater, actual heavy' => ['greater', 11, true],
]);

test('parcel fee on volume', function () {
    $profile = ($this->fee)($this->profile, 1, [
        'charge'     => 'per_entity',
        'basis'      => 'volume',
        'measure'    => null,
        'unit'       => 'm3',
        'thresholds' => [['over' => '0.1', 'amount' => 700]],
    ]);
    $entity = new EntityInput(length: 50, width: 50, height: 50, dimensionsUnit: 'cm');

    $quote = Orders::price(['profile' => $profile, 'entities' => [$entity]]);

    expect(Orders::lines($quote)[1])->toBe(['PARCEL', 'Parcel fee', 1, 700, 700]);
});

test('parcel fee on the order total', function (string $charge, array $line) {
    $profile = ($this->fee)($this->profile, 1, [
        'scope'      => 'order',
        'charge'     => $charge,
        'thresholds' => [['over' => 30, 'amount' => $charge === 'per_order' ? 1500 : 40]],
    ]);

    $quote = Orders::price(['profile' => $profile, 'entities' => [Orders::entity(20), Orders::entity(15)]]);

    expect(Orders::lines($quote)[1])->toBe($line);
})->with([
    'once per order' => ['per_order', ['PARCEL', 'Parcel fee', 1, 1500, 1500]],
    'per unit over'  => ['per_unit', ['PARCEL', 'Parcel fee (5 kg)', 1, 200, 200]],
]);

test('an entity with no weight is not checked, and the quote says so', function () {
    $profile = ($this->fee)($this->profile, 1, []);

    $quote = Orders::price(['profile' => $profile, 'entities' => [Orders::entity(null)]]);

    expect($quote->total())->toBe(2700)
        ->and($quote->notes)->toBe([[
            'key'    => 'enhanced-pricing.pricing.entity-not-measured',
            'params' => ['entity' => 1, 'rule' => 'Parcel fee', 'rule_key' => 'heavy'],
        ]]);
});

test('a per-entity fee counts only entities billed under its products', function () {
    $profile = ($this->fee)($this->profile, 1, ['applies_to' => ['SEPARATE']]);

    $quote = Orders::price(['profile' => $profile, 'entities' => [Orders::entity(40), Orders::entity(35, 'separate')]]);

    expect(array_slice(Orders::lines($quote), 2))->toBe([['PARCEL', 'Parcel fee (5 kg)', 1, 200, 200]]);
});

test(
    'an additional fee on an entity attribute',
    function (array $attribute, array $fields, array $values, array $line) {
        $profile  = Profiles::append($this->profile, 'attributes', $attribute);
        $profile  = ($this->fee)($profile, 0, ['attribute' => $attribute['key']] + $fields);
        $entities = array_map(
            static fn ($value) => Orders::entity(5, null, $value === null ? [] : [$attribute['key'] => $value]),
            $values,
        );

        $quote = Orders::price(['profile' => $profile, 'entities' => $entities]);

        $additional = array_values(array_filter(
            Orders::lines($quote),
            static fn (array $item): bool => $item[0] === 'ADDITIONAL',
        ));

        expect($additional)->toBe($line === [] ? [] : [$line]);
    },
)->with([
    'boolean, per entity' => [
        ['key' => 'fragile', 'name' => 'Fragile', 'scope' => 'entity', 'type' => 'boolean'],
        ['charge' => 'per_entity', 'amount' => 300],
        [true, false, true, null],
        ['ADDITIONAL', 'Additional fee', 2, 300, 600],
    ],
    'choice, once per order' => [
        [
            'key'     => 'size',
            'name'    => 'Size',
            'scope'   => 'entity',
            'type'    => 'choice',
            'choices' => [['key' => 's', 'name' => 'Small'], ['key' => 'l', 'name' => 'Large']],
        ],
        ['charge' => 'per_order', 'amount' => 900, 'equals' => 'l'],
        ['s', 'l', 'l'],
        ['ADDITIONAL', 'Additional fee', 1, 900, 900],
    ],
    'choice, no match' => [
        [
            'key'     => 'size',
            'name'    => 'Size',
            'scope'   => 'entity',
            'type'    => 'choice',
            'choices' => [['key' => 's', 'name' => 'Small'], ['key' => 'l', 'name' => 'Large']],
        ],
        ['charge' => 'per_order', 'amount' => 900, 'equals' => 'l'],
        ['s', null],
        [],
    ],
    'number, per unit' => [
        ['key' => 'hours', 'name' => 'Hours', 'scope' => 'entity', 'type' => 'number', 'unit' => 'h'],
        ['charge' => 'per_unit', 'amount' => 1000],
        ['2.5', 0],
        ['ADDITIONAL', 'Additional fee (2.5 h)', 1, 2500, 2500],
    ],
]);

test('an additional fee on an order attribute', function (array $attributes, int $total) {
    $profile = ($this->fee)($this->profile, 0, []);

    expect(Orders::price(['profile' => $profile, 'attributes' => $attributes])->total())->toBe($total);
})->with([
    'true'    => [['extra' => true], 2700 + 1600],
    'false'   => [['extra' => false], 2700],
    'missing' => [[], 2700],
]);

test('an order attribute charged per entity charges each entity on its products', function () {
    $profile = ($this->fee)($this->profile, 0, ['charge' => 'per_entity', 'amount' => 100, 'applies_to' => ['TIERED']]);

    $quote = Orders::price([
        'profile'    => $profile,
        'attributes' => ['extra' => true],
        'entities'   => [Orders::entity(5), Orders::entity(5), Orders::entity(5, 'separate')],
    ]);

    expect(array_slice(Orders::lines($quote), 2))->toBe([['ADDITIONAL', 'Additional fee', 2, 100, 200]]);
});

test('a per-order fee needs a line of more than $0 for one of its products', function () {
    $profile = ($this->fee)($this->profile, 0, ['applies_to' => ['SEPARATE']]);

    $quote = Orders::price(['profile' => $profile, 'attributes' => ['extra' => true]]);

    expect($quote->total())->toBe(2700);
});

test('a manual charge line can make a per-order fee apply', function () {
    $profile = ($this->fee)($this->profile, 0, ['applies_to' => ['HOURLY']]);

    $quote = Orders::price([
        'profile'    => $profile,
        'attributes' => ['extra' => true],
        'manual'     => [ManualChargeInput::new('hourly', quantity: '1')],
    ]);

    expect(Orders::lines($quote)[2])->toBe(['ADDITIONAL', 'Additional fee', 1, 1600, 1600]);
});

test('a missing amount stops pricing, or is skipped', function () {
    $profile = ($this->fee)($this->profile, 0, ['amount' => null]);

    expect(fn () => Orders::price(['profile' => $profile, 'attributes' => ['extra' => true]]))
        ->toThrow(PricingFailed::class, 'enhanced-pricing.pricing.missing-amount');

    $skipping = Profiles::set($profile, 'fees.0.if_missing', 'skip');

    expect(Orders::price(['profile' => $skipping, 'attributes' => ['extra' => true]])->total())->toBe(2700);
});

test('a missing amount is not looked up when the fee does not match', function () {
    $profile = ($this->fee)($this->profile, 0, ['amount' => null]);

    expect(Orders::price(['profile' => $profile, 'attributes' => ['extra' => false]])->total())->toBe(2700);
});

test('a minimum or maximum applies to the fee total and makes one line', function (array $fields, array $line) {
    $profile = ($this->fee)($this->profile, 2, ['charge' => 'per_entity', 'amount' => 500] + $fields);

    $quote = Orders::price([
        'profile'  => $profile,
        'pickup'   => Orders::SATURDAY,
        'entities' => array_fill(0, 3, Orders::entity(5)),
    ]);

    expect(Orders::lines($quote)[2])->toBe($line);
})->with([
    'maximum'           => [['maximum' => 1000], ['PEAK', 'Peak hours fee', 1, 1000, 1000]],
    'minimum'           => [['minimum' => 2000], ['PEAK', 'Peak hours fee', 1, 2000, 2000]],
    'within the limits' => [['minimum' => 100, 'maximum' => 9000], ['PEAK', 'Peak hours fee', 3, 500, 1500]],
]);

test('a fee is in effect from and until its dates, inclusive', function (string $pickup, bool $charged) {
    $profile = ($this->fee)($this->profile, 2, [
        'weekdays'        => null,
        'window'          => ['from' => '00:00', 'to' => '23:59'],
        'effective_from'  => '2027-03-02',
        'effective_until' => '2027-03-31',
    ]);

    expect(Orders::price(['profile' => $profile, 'pickup' => $pickup])->total())->toBe($charged ? 4900 : 2700);
})->with([
    'the day before'         => ['2027-03-01 12:00', false],
    'on the first day'       => ['2027-03-02 00:00', true],
    'on the last day, 23:58' => ['2027-03-31 23:58', true],
    'the day after'          => ['2027-04-01 00:00', false],
]);

test('a customer can turn a fee off or on, or point it at other products', function (array $overrides, int $total) {
    $profile = ($this->fee)($this->profile, 0, []);
    $options = ['profile' => $profile, 'attributes' => ['extra' => true], 'overrides' => $overrides];

    expect(Orders::price($options)->total())->toBe($total);
})->with([
    'turned off'           => [['fees.extra_handling.enabled' => false], 2700],
    'other products'       => [['fees.extra_handling.applies_to' => ['DISTANCE']], 2700],
    'an override of 0'     => [['fees.extra_handling.amount' => 0], 2700],
    'an override amount'   => [['fees.extra_handling.amount' => 1000], 3700],
]);

test('a fee the profile turns off can be turned on for one customer', function () {
    $quote = Orders::price([
        'profile'    => $this->profile,
        'attributes' => ['extra' => true],
        'overrides'  => ['fees.extra_handling.enabled' => true],
    ]);

    expect($quote->total())->toBe(4300);
});

test('a required attribute that is missing stops pricing', function (
    string $field,
    mixed $required,
    string $rate,
    bool $stops,
) {
    $profile = Profiles::set($this->profile, "attributes.0.$field", $required);
    $options = ['profile' => $profile, 'service_rate' => $rate, 'distance_meters' => 10000];
    if ($rate === 'distance') {
        $options['row'] = null;
    }

    if ($stops) {
        expect(fn () => Orders::price($options))
            ->toThrow(PricingFailed::class, 'enhanced-pricing.pricing.attribute-required');
    } else {
        expect(Orders::price($options)->total())->toBeGreaterThan(0);
    }
})->with([
    'always'                       => ['required', true, 'tiered', true],
    'for this service rate'        => ['required_for', ['tiered'], 'tiered', true],
    'for another service rate'     => ['required_for', ['tiered'], 'distance', false],
    'not required'                 => ['required', false, 'tiered', false],
]);

test('an attribute value of the wrong kind stops pricing', function (array $attributes) {
    Orders::price(['profile' => $this->profile, 'attributes' => $attributes]);
})->throws(PricingFailed::class, 'enhanced-pricing.pricing.attribute-invalid')->with([
    'a string for a boolean' => [['extra' => 'yes']],
    'an unknown attribute'   => [['nope' => true]],
]);

test(
    'parcel fee per unit is charged in bands between thresholds',
    function (int $kilograms, string $name, int $amount) {
        $profile = ($this->fee)($this->profile, 1, [
            'thresholds' => [['over' => 30, 'amount' => 40], ['over' => 50, 'amount' => 60]],
        ]);

        $quote = Orders::price(['profile' => $profile, 'entities' => [Orders::entity($kilograms)]]);

        expect(Orders::lines($quote)[1])->toBe(['PARCEL', $name, 1, $amount, $amount]);
    },
)->with([
    'in the first band'  => [49, 'Parcel fee (19 kg)', 760],
    'in the second band' => [55, 'Parcel fee (25 kg)', 1100],
]);

test('identical per-unit lines combine into one line with a quantity', function () {
    $profile = ($this->fee)($this->profile, 1, []);

    $quote = Orders::price([
        'profile'  => $profile,
        'entities' => [Orders::entity(36), Orders::entity(36), Orders::entity(31)],
    ]);

    expect(array_slice(Orders::lines($quote), 2))->toBe([
        ['PARCEL', 'Parcel fee (6 kg)', 2, 240, 480],
        ['PARCEL', 'Parcel fee (1 kg)', 1, 40, 40],
    ]);
});

test('an entity billed on a $0 line is not counted', function () {
    $profile = ($this->fee)($this->profile, 1, [
        'charge'     => 'per_entity',
        'thresholds' => [['over' => 1, 'amount' => 100]],
    ]);

    $quote = Orders::price([
        'profile'  => $profile,
        'row'      => ['separate_price' => 0] + Orders::ROW_1,
        'entities' => [Orders::entity(5), Orders::entity(5, 'separate')],
    ]);

    expect(Orders::lines($quote))->toBe([
        ['TIERED', 'Tiered delivery', 1, 2700, 2700],
        ['PARCEL', 'Parcel fee', 1, 100, 100],
    ]);
});

test('an entity measurement that is not a number, negative or too large stops pricing', function (mixed $weight) {
    Orders::price(['profile' => $this->profile, 'entities' => [new EntityInput(weight: $weight, weightUnit: 'kg')]]);
})->throws(PricingFailed::class, 'enhanced-pricing.pricing.entity-invalid')->with([
    'text'      => ['heavy'],
    'negative'  => [-1],
    'too large' => [1000001],
    'exponent'  => ['1e3'],
]);

test('an unknown weight unit stops pricing', function () {
    Orders::price(['profile' => $this->profile, 'entities' => [new EntityInput(weight: 5, weightUnit: 'stone')]]);
})->throws(PricingFailed::class, 'enhanced-pricing.pricing.entity-invalid');

test('a number attribute above its maximum stops pricing', function () {
    $profile = Profiles::append($this->profile, 'attributes', [
        'key'     => 'hours',
        'name'    => 'Hours',
        'scope'   => 'order',
        'type'    => 'number',
        'maximum' => 10,
    ]);

    Orders::price(['profile' => $profile, 'attributes' => ['hours' => 11]]);
})->throws(PricingFailed::class, 'enhanced-pricing.pricing.attribute-invalid');
