<?php

use Fleetbase\EnhancedPricing\Pricing\ManualChargeInput;
use Fleetbase\EnhancedPricing\Pricing\PricingFailed;
use Fleetbase\EnhancedPricing\Profile\ProfileValidator;
use Fleetbase\EnhancedPricing\Tests\Support\BillingProfiles;
use Fleetbase\EnhancedPricing\Tests\Support\Orders;
use Fleetbase\EnhancedPricing\Tests\Support\Profiles;

// A fee, subtotal fee or manual charge may carry its own code and a description. The code is the
// code its lines are billed under (the product's code when it has none); fees still match lines
// by product. The description is the person's own note. Both are optional, so every profile
// saved before they existed stays valid and prices as before.

test('a profile saved without codes or descriptions is valid and normalizes them to null', function () {
    $profile    = Profiles::example();
    $problems   = (new ProfileValidator())->check($profile);
    $normalized = (new ProfileValidator())->normalize($profile);

    expect($problems)->toBe([])
        ->and($normalized['fees'][0]['code'])->toBeNull()
        ->and($normalized['fees'][0]['description'])->toBeNull()
        ->and($normalized['subtotal_fees'][0]['code'])->toBeNull()
        ->and($normalized['manual_charges'][0]['description'])->toBeNull();
});

test('a fee bills under its own code and keeps its product', function () {
    $profile = Profiles::set(Profiles::example(), 'fees.2.code', 'AFTER-HOURS');
    $profile = Profiles::set($profile, 'subtotal_fees.0.code', 'FUEL.1');
    $quote   = Orders::price(['profile' => $profile, 'pickup' => Orders::SATURDAY]);
    $codes   = [];
    foreach ($quote->lines as $line) {
        $codes[$line->product] = $line->billingCode;
    }

    expect($codes['PEAK'])->toBe('AFTER-HOURS')
        ->and($codes['PERCENT'])->toBe('FUEL.1')
        ->and($codes['TIERED'])->toBe('TIERED');
});

test('a manual charge bills under its own code', function () {
    $profile = Profiles::set(Profiles::example(), 'manual_charges.0.code', 'WAIT-1');

    $quote = Orders::price(['profile' => $profile, 'manual' => [ManualChargeInput::new('hourly', quantity: '2')]]);
    $line  = array_values(array_filter($quote->lines, static fn ($line) => $line->product === 'HOURLY'))[0];

    expect($line->billingCode)->toBe('WAIT-1');
});

test('a code may be the rule\'s own product code but not another product\'s', function (string $code, bool $valid) {
    $profile = Profiles::set(Profiles::example(), 'fees.2.code', $code);
    $paths   = array_map(static fn ($problem) => $problem->path . ':' . $problem->key, (new ProfileValidator())->check($profile));

    expect($paths === [])->toBe($valid)
        ->and($valid || $paths === ['fees.2.code:enhanced-pricing.profile.code-is-product'])->toBeTrue();
})->with([
    'its own product'          => ['PEAK', true],
    'its own product, cased'   => ['peak', true],
    'another product'          => ['PARCEL', false],
    'another product, cased'   => ['parcel', false],
    'a code of its own'        => ['NIGHT-1', true],
]);

test('a code must be written like a product code and a description must not be empty', function () {
    $profile = Profiles::set(Profiles::example(), 'fees.2.code', 'not valid!');
    $profile = Profiles::set($profile, 'fees.2.description', '');
    $paths   = array_map(static fn ($problem) => $problem->path, (new ProfileValidator())->check($profile));

    expect($paths)->toContain('fees.2.code')->toContain('fees.2.description');
});

test('a route\'s billing code from the rate card row may not be a fee, subtotal fee or manual charge code', function (string $section, string $code) {
    $profile = Profiles::set(BillingProfiles::profile(), "$section.0.code", 'MILEAGE');

    Orders::price(['profile' => $profile, 'row' => Orders::ROW_1 + ['billing_code' => $code]]);
})->throws(PricingFailed::class, 'enhanced-pricing.pricing.billing-code-is-product')->with([
    'a fee'            => ['fees', 'MILEAGE'],
    'a fee, cased'     => ['fees', 'mileage'],
    'a subtotal fee'   => ['subtotal_fees', 'MILEAGE'],
    'a manual charge'  => ['manual_charges', 'Mileage'],
]);
