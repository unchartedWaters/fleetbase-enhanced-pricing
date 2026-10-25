<?php

use Fleetbase\EnhancedPricing\Pricing\EntityInput;
use Fleetbase\EnhancedPricing\Pricing\PricingEngine;
use Fleetbase\EnhancedPricing\Pricing\PricingRequest;
use Fleetbase\EnhancedPricing\Profile\ProfileValidator;
use Fleetbase\EnhancedPricing\Tests\Support\Docs;
use Fleetbase\EnhancedPricing\Tests\Support\Profiles;

test('the pricing engine example prices as the guide shows', function () {
    $request = new PricingRequest(
        profile: Profiles::example(),
        serviceRate: 'distance',
        pickupAt: new DateTimeImmutable('2027-03-03T15:00:00Z'),
        timezone: 'America/Toronto',
        entities: [new EntityInput(weight: 42, weightUnit: 'kg')],
        distanceMeters: 7000,
    );

    expect((new PricingEngine())->price($request)->toArray())
        ->toBe(Docs::jsonAfter('docs/pricing-engine.md', 'example-quote'));
});

test('the example profile the docs link to has no problems', function () {
    $profile = json_decode(Docs::text('server/tests/Fixtures/example-profile.json'), true);

    expect((new ProfileValidator())->check($profile))->toBe([]);
});

test('every JSON block in the docs parses', function (string $file) {
    $broken = array_filter(Docs::jsonBlocks($file), static fn (string $block): bool => Docs::parses($block) === false);

    expect($broken)->toBe([]);
})->with(Docs::FILES);

test('every relative link in the docs points at a file', function (string $file) {
    $missing = array_filter(
        Docs::relativeLinks($file),
        static fn (string $link): bool => file_exists(dirname(Docs::path($file)) . '/' . $link) === false,
    );

    expect(array_values($missing))->toBe([]);
})->with(Docs::FILES);
