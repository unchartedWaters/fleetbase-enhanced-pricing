<?php

use Fleetbase\EnhancedPricing\Pricing\EntityInput;
use Fleetbase\EnhancedPricing\Pricing\LineBook;
use Fleetbase\EnhancedPricing\Pricing\PricingFailed;
use Fleetbase\EnhancedPricing\Profile\ChangeValidator;
use Fleetbase\EnhancedPricing\Profile\OverrideValidator;
use Fleetbase\EnhancedPricing\Profile\ProfileValidator;
use Fleetbase\EnhancedPricing\Tests\Support\LargeInputs;
use Fleetbase\EnhancedPricing\Tests\Support\Orders;
use Fleetbase\EnhancedPricing\Tests\Support\Profiles;

// Inputs at the documented limits: they price, or stop with a message, and do it quickly.

test('the 64 KB limit is on the profile as saved, not with its defaults filled in', function () {
    $profile    = LargeInputs::nearSizeLimit();
    $normalized = (new ProfileValidator())->normalize($profile);
    $overrides  = ['fees.w0.amount' => 2];
    $change     = ['path' => 'fees.w0.amount', 'value' => 2, 'effective_from' => '2027-01-01'];

    expect(LargeInputs::size($profile))->toBeLessThanOrEqual(ProfileValidator::MAX_BYTES)
        ->and(LargeInputs::size($normalized))->toBeGreaterThan(ProfileValidator::MAX_BYTES)
        ->and((new OverrideValidator())->check($profile, $overrides))->toBe([])
        ->and((new ChangeValidator())->check($profile, [$change]))->toBe([])
        ->and(Orders::price(['profile' => $profile, 'overrides' => $overrides])->total())->toBeGreaterThan(0);
});

test('the 64 KB limit counts text as UTF-8', function () {
    $profile = Profiles::example();
    foreach (array_keys($profile['products']) as $index) {
        $profile['products'][$index]['name'] = str_repeat('é', 255);
    }
    for ($index = count($profile['products']); $index < 50; $index++) {
        $profile['products'][] = ['code' => "NAMED_$index", 'name' => str_repeat('é', 255)];
    }

    expect(strlen((string) json_encode($profile)))->toBeGreaterThan(ProfileValidator::MAX_BYTES)
        ->and(LargeInputs::size($profile))->toBeLessThan(ProfileValidator::MAX_BYTES)
        ->and((new ProfileValidator())->check($profile))->toBe([]);
});

test('an amount too large for an integer stops pricing with a message', function () {
    $profile = Profiles::set(Profiles::example(), 'fees.1.basis', 'volume');
    $profile = Profiles::set($profile, 'fees.1.unit', 'cm3');
    $profile = Profiles::set($profile, 'fees.1.measure', null);
    $entity  = new EntityInput(
        weight: 1,
        weightUnit: 'kg',
        length: 1000000,
        width: 1000000,
        height: 1000000,
        dimensionsUnit: 'm',
    );

    Orders::price(['profile' => $profile, 'entities' => [$entity]]);
})->throws(PricingFailed::class, 'enhanced-pricing.pricing.amount-too-large');

test('many fees on many entities price quickly', function () {
    $started = microtime(true);
    $quote   = Orders::price([
        'profile'  => LargeInputs::perUnitFees(9),
        'entities' => LargeInputs::entities(1000),
        'row'      => ['code' => 'R', 'first' => 1, 'second' => 1, 'third' => 1],
    ]);

    expect(count($quote->lines))->toBe(9001)
        ->and(microtime(true) - $started)->toBeLessThan(5.0);
});

test('a quote of more than 10,000 lines stops pricing, quickly', function () {
    $started = microtime(true);
    try {
        Orders::price([
            'profile'  => LargeInputs::perUnitFees(50),
            'entities' => LargeInputs::entities(1000),
            'row'      => ['code' => 'R', 'first' => 1, 'second' => 1, 'third' => 1],
        ]);
        $key = null;
    } catch (PricingFailed $failed) {
        $key = $failed->key;
    }

    expect($key)->toBe('enhanced-pricing.pricing.too-many-lines')
        ->and(LineBook::MAX_LINES)->toBe(10000)
        ->and(microtime(true) - $started)->toBeLessThan(5.0);
});

test('1,000 dated changes do not slow pricing down', function () {
    $profile = LargeInputs::nearSizeLimit();
    $changes = [];
    $date    = new DateTimeImmutable('2020-01-01');
    for ($index = 0; $index < 1000; $index++) {
        $changes[] = ['path' => 'fees.w0.amount', 'value' => $index + 1, 'effective_from' => $date->format('Y-m-d')];
        $date      = $date->modify('+1 day');
    }

    $started = microtime(true);
    Orders::price(['profile' => $profile, 'changes' => $changes]);

    expect(microtime(true) - $started)->toBeLessThan(1.0);
});

test('a float measurement is read as its shortest exact form', function (float $kilograms, string $same) {
    $profile = Profiles::set(Profiles::example(), 'fees.1.unit', 'g');

    expect(Orders::lines(Orders::price([
        'profile'  => $profile,
        'entities' => [new EntityInput(weight: $kilograms, weightUnit: 'kg')],
    ])))
        ->toBe(Orders::lines(Orders::price([
            'profile'  => $profile,
            'entities' => [new EntityInput(weight: $same, weightUnit: 'kg')],
        ])));
})->with([
    'a tenth'        => [0.1, '0.1'],
    'a small number' => [0.00005, '0.00005'],
    'a large number' => [1234.25, '1234.25'],
]);

test('a float with more than 6 decimals stops pricing rather than being cut', function (float $kilograms) {
    Orders::price(['entities' => [new EntityInput(weight: $kilograms, weightUnit: 'kg')]]);
})->throws(PricingFailed::class, 'enhanced-pricing.pricing.entity-invalid')->with([
    'just over 30' => [30.000000000000004],
    'seven places' => [30.0000001],
]);
