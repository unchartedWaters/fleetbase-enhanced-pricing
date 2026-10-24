<?php

use Fleetbase\EnhancedPricing\Pricing\PricingFailed;
use Fleetbase\EnhancedPricing\Tests\Support\Orders;
use Fleetbase\EnhancedPricing\Tests\Support\Profiles;

// Service fees: tiers, per meter, fixed rate, minimums, rows and which service rates a customer
// may order. Subtotal fees are turned off here so each test sees only the service fee.

beforeEach(function () {
    $profile = Profiles::example();
    foreach ([0, 1, 2] as $index) {
        $profile = Profiles::set($profile, "subtotal_fees.$index.enabled", false);
    }
    $this->profile = $profile;
});

test('tiers can take their limit and price from row columns', function () {
    $profile = Profiles::append($this->profile, 'rate_tables.0.columns', [
        'key'      => 'included',
        'type'     => 'integer',
        'required' => true,
    ]);
    $profile = Profiles::set($profile, 'service_rates.0.tiers', [
        ['up_to' => ['column' => 'included'], 'price' => ['column' => 'first']],
        ['up_to' => null, 'price' => ['column' => 'second']],
    ]);

    $quote = Orders::price([
        'profile'  => $profile,
        'row'      => Orders::ROW_1 + ['included' => 1],
        'entities' => [Orders::entity(5), Orders::entity(5), Orders::entity(5)],
    ]);

    expect(Orders::lines($quote))->toBe([
        ['TIERED', 'Tiered delivery', 1, 2700, 2700],
        ['TIERED', 'Tiered delivery', 2, 1800, 3600],
    ]);
});

test('a tier that covers no entities makes no line', function () {
    $quote = Orders::price(['profile' => $this->profile, 'entities' => [Orders::entity(5), Orders::entity(5)]]);

    expect(Orders::lines($quote))->toBe([['TIERED', 'Tiered delivery', 2, 2700, 5400]]);
});

test('more entities than a closed last tier allows stops pricing', function () {
    $profile = Profiles::set($this->profile, 'service_rates.0.tiers.2.up_to', ['value' => 6]);

    Orders::price(['profile' => $profile, 'entities' => array_fill(0, 7, Orders::entity(5))]);
})->throws(PricingFailed::class, 'enhanced-pricing.pricing.too-many-entities-for-tiers');

test('a tiered service rate needs at least one entity', function () {
    Orders::price(['profile' => $this->profile, 'entities' => []]);
})->throws(PricingFailed::class, 'enhanced-pricing.pricing.no-entities');

test('the line name can come from a text column of the row', function () {
    $profile = Profiles::set($this->profile, 'service_rates.0.line_name', ['column' => 'code']);

    $quote = Orders::price(['profile' => $profile]);

    expect(Orders::lines($quote))->toBe([['TIERED', 'R1', 1, 2700, 2700]]);
});

test('an entity type priced at its own column is its own line under its own product', function () {
    $quote = Orders::price([
        'profile'  => $this->profile,
        'entities' => [Orders::entity(5), Orders::entity(5, 'separate'), Orders::entity(5, 'separate')],
    ]);

    expect(Orders::lines($quote))->toBe([
        ['TIERED', 'Tiered delivery', 1, 2700, 2700],
        ['SEPARATE', 'Separate entity type', 2, 900, 1800],
    ]);
});

test('a blank column that a rule needs stops pricing and names the customer, card, row and rule', function () {
    $row = Orders::ROW_1;
    unset($row['separate_price']);

    try {
        Orders::price(['profile' => $this->profile, 'row' => $row, 'entities' => [Orders::entity(5, 'separate')]]);
        $this->fail('Pricing should have stopped.');
    } catch (PricingFailed $failure) {
        expect($failure->key)->toBe('enhanced-pricing.pricing.missing-amount')
            ->and($failure->params)->toMatchArray([
                'customer' => 'Example customer',
                'card'     => 'Main',
                'row'      => 'R1',
                'rule'     => 'separate',
                'column'   => 'separate_price',
            ]);
    }
});

test('a blank column that no rule needs does not stop pricing', function () {
    $row = Orders::ROW_1;
    unset($row['separate_price']);

    $quote = Orders::price(['profile' => $this->profile, 'row' => $row]);

    expect($quote->total())->toBe(2700);
});

test('on a per-meter service rate every entity is billed under its product', function () {
    $quote = Orders::price([
        'profile'         => $this->profile,
        'service_rate'    => 'distance',
        'row'             => null,
        'distance_meters' => 20000,
        'entities'        => [Orders::entity(5), Orders::entity(5, 'separate')],
    ]);

    expect(Orders::lines($quote))->toBe([['DISTANCE', 'Distance (20 km)', 1, 5600, 5600]]);
});

test('per meter rounding', function (string $rounding, int $meters, string $name, int $amount) {
    $profile = Profiles::set($this->profile, 'service_rates.1.per_meter.rounding', $rounding);

    $quote = Orders::price([
        'profile'         => $profile,
        'service_rate'    => 'distance',
        'row'             => null,
        'distance_meters' => $meters,
    ]);

    expect(Orders::lines($quote))->toBe([['DISTANCE', $name, 1, $amount, $amount]]);
})->with([
    'up'                   => ['up', 12100, 'Distance (13 km)', 4130],
    'nearest, below half'  => ['nearest', 12499, 'Distance (12 km)', 3920],
    'nearest, half'        => ['nearest', 12500, 'Distance (13 km)', 4130],
    'none'                 => ['none', 12300, 'Distance (12.3 km)', 3983],
]);

test('a per-meter rate in miles converts the driving distance', function () {
    $profile = Profiles::set($this->profile, 'distance_unit', 'mi');

    $quote = Orders::price([
        'profile'         => $profile,
        'service_rate'    => 'distance',
        'row'             => null,
        'distance_meters' => 16094,
    ]);

    expect(Orders::lines($quote))->toBe([['DISTANCE', 'Distance (11 mi)', 1, 3710, 3710]]);
});

test('a per-meter rate refuses a missing, zero or too long distance', function (?int $meters, string $key) {
    expect(fn () => Orders::price([
        'profile'         => $this->profile,
        'service_rate'    => 'distance',
        'row'             => null,
        'distance_meters' => $meters,
    ]))
        ->toThrow(PricingFailed::class, $key);
})->with([
    'missing'  => [null, 'enhanced-pricing.pricing.distance-required'],
    'zero'     => [0, 'enhanced-pricing.pricing.distance-required'],
    'too long' => [1000001, 'enhanced-pricing.pricing.distance-too-long'],
]);

test('a fixed rate from a value or a column', function (array $amount, int $cents) {
    $profile = Profiles::set($this->profile, 'service_rates.0', [
        'key'        => 'tiered',
        'name'       => 'Fixed',
        'product'    => 'TIERED',
        'rate_table' => 'rows',
        'line_name'  => ['text' => 'Fixed'],
        'method'     => 'fixed_rate',
        'fixed_rate' => ['amount' => $amount],
    ]);

    $quote = Orders::price(['profile' => $profile, 'entities' => [Orders::entity(5), Orders::entity(5)]]);

    expect(Orders::lines($quote))->toBe([['TIERED', 'Fixed', 1, $cents, $cents]]);
})->with([
    'value'  => [['value' => 4500], 4500],
    'column' => [['column' => 'second'], 1800],
]);

test('a minimum replaces the service fee lines with one line', function () {
    $profile = Profiles::set($this->profile, 'service_rates.0.minimum', 6000);

    $quote = Orders::price(['profile' => $profile, 'entities' => [Orders::entity(5), Orders::entity(5, 'separate')]]);

    expect(Orders::lines($quote))->toBe([
        ['TIERED', 'Tiered delivery', 1, 6000, 6000],
        ['SEPARATE', 'Separate entity type', 1, 900, 900],
    ]);
});

test('a minimum below the service fee changes nothing', function () {
    $profile = Profiles::set($this->profile, 'service_rates.0.minimum', 1000);

    expect(Orders::lines(Orders::price(['profile' => $profile])))->toBe([['TIERED', 'Tiered delivery', 1, 2700, 2700]]);
});

test('a minimum does not raise a $0 service fee', function () {
    $profile = Profiles::set($this->profile, 'service_rates.0.minimum', 5000);

    $quote = Orders::price([
        'profile' => $profile,
        'row'     => ['first' => 0, 'second' => 0, 'third' => 0] + Orders::ROW_1,
    ]);

    expect(Orders::lines($quote))->toBe([]);
});

test('the maximum distance is checked before rounding', function () {
    $profile = Profiles::set($this->profile, 'service_rates.1.per_meter.rounding', 'nearest');

    Orders::price(['profile' => $profile, 'service_rate' => 'distance', 'row' => null, 'distance_meters' => 1000400]);
})->throws(PricingFailed::class, 'enhanced-pricing.pricing.distance-too-long');

test('a service rate with a rate table needs a row', function () {
    Orders::price(['profile' => $this->profile, 'row' => null]);
})->throws(PricingFailed::class, 'enhanced-pricing.pricing.row-required');

test('a row value of the wrong type stops pricing', function () {
    Orders::price(['profile' => $this->profile, 'row' => ['first' => '27.00'] + Orders::ROW_1]);
})->throws(PricingFailed::class, 'enhanced-pricing.pricing.row-invalid');

test('a row missing a required column stops pricing', function () {
    $row = Orders::ROW_1;
    unset($row['third']);

    Orders::price(['profile' => $this->profile, 'row' => $row]);
})->throws(PricingFailed::class, 'enhanced-pricing.pricing.row-invalid');

test('service rates a customer may not order', function (array $profileEdits, array $overrides, string $rate) {
    $profile = $this->profile;
    foreach ($profileEdits as $path => $value) {
        $profile = Profiles::set($profile, $path, $value);
    }

    Orders::price(['profile' => $profile, 'overrides' => $overrides, 'service_rate' => $rate]);
})->throws(PricingFailed::class, 'enhanced-pricing.pricing.service-rate-not-allowed')->with([
    'disabled in the profile'  => [['service_rates.0.enabled' => false], [], 'tiered'],
    'disabled by an override'  => [[], ['service_rates.tiered.enabled' => false], 'tiered'],
    'not in the allowed list'  => [[], ['allowed_service_rates' => ['distance']], 'tiered'],
]);

test('an override can enable a service rate the profile disables', function () {
    $profile = Profiles::set($this->profile, 'service_rates.0.enabled', false);

    $quote = Orders::price(['profile' => $profile, 'overrides' => ['service_rates.tiered.enabled' => true]]);

    expect($quote->total())->toBe(2700);
});

test('an unknown service rate stops pricing', function () {
    Orders::price(['profile' => $this->profile, 'service_rate' => 'nope']);
})->throws(PricingFailed::class, 'enhanced-pricing.pricing.unknown-service-rate');

test('an unknown entity type stops pricing', function () {
    Orders::price(['profile' => $this->profile, 'entities' => [Orders::entity(5, 'nope')]]);
})->throws(PricingFailed::class, 'enhanced-pricing.pricing.unknown-entity-type');

test('an entity without a type is priced as the default type', function () {
    $quote = Orders::price(['profile' => $this->profile, 'entities' => [Orders::entity(5, null)]]);

    expect(Orders::lines($quote))->toBe([['TIERED', 'Tiered delivery', 1, 2700, 2700]]);
});

test('more than 1000 entities stops pricing', function () {
    Orders::price(['profile' => $this->profile, 'entities' => array_fill(0, 1001, Orders::entity(5))]);
})->throws(PricingFailed::class, 'enhanced-pricing.pricing.too-many-entities');

test('an invalid profile is refused before pricing', function () {
    $profile = Profiles::set($this->profile, 'currency', 'dollars');

    Orders::price(['profile' => $profile]);
})->throws(PricingFailed::class, 'enhanced-pricing.pricing.profile-invalid');
