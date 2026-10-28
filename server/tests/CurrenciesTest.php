<?php

use Fleetbase\EnhancedPricing\Profile\ProfileValidator;
use Fleetbase\EnhancedPricing\Support\MinorUnits;
use Fleetbase\EnhancedPricing\Tests\Support\Docs;
use Fleetbase\EnhancedPricing\Tests\Support\Orders;
use Fleetbase\EnhancedPricing\Tests\Support\Profiles;

// Money is an integer count of the profile currency's minor unit: cents for USD, whole yen for
// JPY, fils for KWD. The engine never assumes two decimals, so the same integers price the same
// way in any currency and each line is rounded half up to one minor unit.

test('a profile in a currency without two decimals is valid and quotes in it', function (string $currency) {
    $profile = Profiles::set(Profiles::example(), 'currency', $currency);

    expect((new ProfileValidator())->check($profile))->toBe([]);
    expect(Orders::price(['profile' => $profile])->currency)->toBe($currency);
})->with(['JPY', 'KWD']);

test('each line rounds half up to one minor unit, whatever the currency', function (string $currency) {
    $profile = Profiles::example();
    foreach ([0, 1, 2] as $index) {
        $profile = Profiles::set($profile, "subtotal_fees.$index.enabled", $index === 0);
        $profile = Profiles::set($profile, "fees.$index.enabled", false);
    }
    $profile = Profiles::set($profile, 'currency', $currency);
    $profile = Profiles::set($profile, 'subtotal_fees.0.applies_to', ['SEPARATE']);
    $profile = Profiles::set($profile, 'subtotal_fees.0.percentage', 1150);

    $quote = Orders::price(['profile' => $profile, 'entities' => [Orders::entity(5), Orders::entity(5, 'separate')]]);

    // 11.5% of 900 is 103.5, so ¥104, 0.104 KWD or $1.04.
    expect(Orders::lines($quote)[2])->toBe(['PERCENT', 'Percentage subtotal fee', 1, 104, 104]);
})->with(['JPY', 'KWD', 'USD']);

test("a currency's minor unit is its ISO 4217 exponent", function (string $currency, int $places) {
    expect(MinorUnits::exponent($currency))->toBe($places);
})->with([
    'USD' => ['USD', 2],
    'CAD' => ['CAD', 2],
    'JPY' => ['JPY', 0],
    'KRW' => ['KRW', 0],
    'KWD' => ['KWD', 3],
    'IQD' => ['IQD', 3],
    'CLF' => ['CLF', 4],
    'lowercase' => ['jpy', 0],
    'unknown' => ['ZZZ', 2],
]);

test('an amount in minor units is written as a plain decimal in its currency', function (int $amount, string $currency, string $text) {
    expect(MinorUnits::decimal($amount, $currency))->toBe($text);
})->with([
    [39500, 'USD', '395.00'],
    [5, 'USD', '0.05'],
    [39500, 'JPY', '39500'],
    [0, 'JPY', '0'],
    [1234, 'KWD', '1.234'],
    [5, 'KWD', '0.005'],
    [12345, 'CLF', '1.2345'],
]);

test('the console and the server agree on every minor unit', function () {
    preg_match('/const MINOR_UNITS = \{(.*?)\};/s', Docs::text('addon/utils/money.js'), $match);
    preg_match_all('/([A-Z]{3}): (\d)/', $match[1] ?? '', $pairs, PREG_SET_ORDER);
    $console = [];
    foreach ($pairs as [, $code, $places]) {
        $console[$code] = (int) $places;
    }

    expect($console)->toBe(MinorUnits::NOT_TWO);
});
