<?php

use Fleetbase\EnhancedPricing\Pricing\PricingFailed;
use Fleetbase\EnhancedPricing\Tests\Support\Orders;
use Fleetbase\EnhancedPricing\Tests\Support\Profiles;

// How lines are combined, left off and capped, and what a line records.

beforeEach(function () {
    $profile = Profiles::example();
    foreach ([0, 1, 2] as $index) {
        $profile = Profiles::set($profile, "subtotal_fees.$index.enabled", false);
    }
    $this->profile = $profile;
});

test('lines of two rules with the same product, name and unit amount combine', function () {
    $second        = Profiles::example()['fees'][2];
    $second['key'] = 'peak_two';
    $profile       = Profiles::append($this->profile, 'fees', $second);

    $quote = Orders::price(['profile' => $profile, 'pickup' => Orders::SATURDAY]);

    expect(Orders::lines($quote))->toBe([
        ['TIERED', 'Tiered delivery', 1, 2700, 2700],
        ['PEAK', 'Peak hours fee', 2, 2200, 4400],
    ])->and($quote->lines[1]->sources)->toBe(['fee:peak', 'fee:peak_two']);
});

test('tier lines at the same price combine', function () {
    $quote = Orders::price([
        'profile'  => $this->profile,
        'row'      => ['second' => 2700] + Orders::ROW_1,
        'entities' => array_fill(0, 4, Orders::entity(5)),
    ]);

    expect(Orders::lines($quote))->toBe([['TIERED', 'Tiered delivery', 4, 2700, 10800]]);
});

test('a $0 line is left off', function () {
    $profile = Profiles::set($this->profile, 'fees.2.amount', 0);

    $quote = Orders::price(['profile' => $profile, 'pickup' => Orders::SATURDAY]);

    expect(Orders::lines($quote))->toBe([['TIERED', 'Tiered delivery', 1, 2700, 2700]]);
});

test('a line or a total above $1,000,000 stops pricing', function (int $first, int $count) {
    Orders::price([
        'profile'  => $this->profile,
        'row'      => ['first' => $first, 'second' => $first, 'third' => $first] + Orders::ROW_1,
        'entities' => array_fill(0, $count, Orders::entity(5)),
    ]);
})->throws(PricingFailed::class, 'enhanced-pricing.pricing.amount-too-large')->with([
    'one line'  => [100000000, 2],
    'the total' => [60000000, 2],
]);

test('each line records where it came from', function () {
    $quote = Orders::price([
        'profile'  => Profiles::example(),
        'entities' => [Orders::entity(5), Orders::entity(5, 'separate')],
    ]);

    expect(array_map(static fn ($line) => $line->sources, $quote->lines))->toBe([
        ['service_rate:tiered'],
        ['entity_type:separate'],
        ['subtotal_fee:percent'],
    ]);
});

test('a quote has the profile currency', function () {
    expect(Orders::price(['profile' => $this->profile])->currency)->toBe('USD');
});
