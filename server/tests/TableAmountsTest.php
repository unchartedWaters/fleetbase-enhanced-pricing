<?php

use Fleetbase\EnhancedPricing\Pricing\PricingFailed;
use Fleetbase\EnhancedPricing\Profile\ChangeValidator;
use Fleetbase\EnhancedPricing\Profile\OverrideValidator;
use Fleetbase\EnhancedPricing\Profile\ProfileValidator;
use Fleetbase\EnhancedPricing\Tests\Support\Orders;
use Fleetbase\EnhancedPricing\Tests\Support\Profiles;

// A fee's amount is a number of minor units, written as a plain number or as { "value": n }, or
// a money column of a rate table: { "column": "second", "rate_table": "rows" }. A column is read
// from the order's rate card row, so the order's service rate must use that table. Peak hours
// (fee 2), additional (fee 0) and flat subtotal (subtotal fee 1) fees take it; only the
// one under test is on. Row 1 holds $18 in "second".

beforeEach(function () {
    $profile = Profiles::example();
    foreach ([0, 1, 2] as $index) {
        $profile = Profiles::set($profile, "fees.$index.enabled", false);
    }
    foreach ([0, 2] as $index) {
        $profile = Profiles::set($profile, "subtotal_fees.$index.enabled", false);
    }
    $this->profile = $profile;
    $this->column  = ['column' => 'second', 'rate_table' => 'rows'];
    $this->price   = static fn (array $profile, array $options = []) => Orders::price($options + [
        'profile' => $profile,
        'pickup'  => Orders::SATURDAY,
    ]);
});

test('a peak hours fee amount as a number, a value or a column', function (mixed $amount, int $cents) {
    $profile = Profiles::set($this->profile, 'fees.2.enabled', true);
    $profile = Profiles::set($profile, 'fees.2.amount', $amount === 'column' ? $this->column : $amount);

    $quote = ($this->price)($profile);

    expect(Orders::lines($quote)[1])->toBe(['PEAK', 'Peak hours fee', 1, $cents, $cents]);
})->with([
    'a number' => [2500, 2500],
    'a value'  => [['value' => 2500], 2500],
    'a column' => ['column', 1800],
]);

test('an additional fee amount from a column', function () {
    $profile = Profiles::set($this->profile, 'fees.0.enabled', true);
    $profile = Profiles::set($profile, 'fees.0.amount', $this->column);

    $quote = ($this->price)($profile, ['attributes' => ['extra' => true]]);

    expect(Orders::lines($quote)[1])->toBe(['ADDITIONAL', 'Additional fee', 1, 1800, 1800]);
});

test('a flat subtotal fee amount from a column', function () {
    $profile = Profiles::set($this->profile, 'subtotal_fees.1.amount', $this->column);
    $profile = Profiles::set($profile, 'subtotal_fees.1.requires', []);
    $profile = Profiles::set($profile, 'subtotal_fees.1.applies_to', ['TIERED']);

    $quote = ($this->price)($profile);

    expect(Orders::lines($quote)[1])->toBe(['FLAT_A', 'Flat subtotal fee A', 1, 1800, 1800]);
});

test('a blank cell stops pricing, or skips the fee when the fee says skip', function (string $missing, bool $stops) {
    $profile = Profiles::set($this->profile, 'fees.2.enabled', true);
    $profile = Profiles::set($profile, 'fees.2.amount', ['column' => 'separate_price', 'rate_table' => 'rows']);
    $profile = Profiles::set($profile, 'fees.2.if_missing', $missing);
    $row     = ['separate_price' => null] + Orders::ROW_1;

    $price = fn () => ($this->price)($profile, ['row' => $row]);

    if ($stops) {
        expect($price)->toThrow(PricingFailed::class, 'enhanced-pricing.pricing.missing-row-amount');
    } else {
        expect($price()->total())->toBe(2700);
    }
})->with([
    'stop' => ['stop', true],
    'skip' => ['skip', false],
]);

test('a row from another table stops pricing, or leaves the fee off', function (string $missing) {
    $profile = Profiles::append($this->profile, 'rate_tables', [
        'key'     => 'other',
        'columns' => [['key' => 'code', 'type' => 'text', 'required' => true]],
        'row_key' => ['code'],
    ]);
    $profile = Profiles::set($profile, 'service_rates.1.rate_table', 'other');
    $profile = Profiles::set($profile, 'fees.2.enabled', true);
    $profile = Profiles::set($profile, 'fees.2.amount', $this->column);
    $profile = Profiles::set($profile, 'fees.2.if_missing', $missing);
    $price   = fn () => ($this->price)($profile, [
        'service_rate'    => 'distance',
        'distance_meters' => 1000,
        'row'             => ['code' => 'X'],
    ]);

    if ($missing === 'stop') {
        expect($price)->toThrow(PricingFailed::class, 'enhanced-pricing.pricing.row-from-other-table');
    } else {
        expect(array_column(Orders::lines($price()), 0))->not->toContain('PEAK');
    }
})->with(['stop', 'skip']);

test('a table no service rate uses is refused', function () {
    $profile = Profiles::append(Profiles::example(), 'rate_tables', [
        'key'     => 'other',
        'columns' => [['key' => 'fee', 'type' => 'money', 'required' => false]] + [
            1 => ['key' => 'code', 'type' => 'text', 'required' => true],
        ],
        'row_key' => ['code'],
    ]);
    $profile = Profiles::set($profile, 'fees.2.amount', ['column' => 'fee', 'rate_table' => 'other']);

    $problems = (new ProfileValidator())->check($profile);

    expect(array_map(static fn ($problem) => [$problem->path, $problem->key], $problems))
        ->toBe([['fees.2.amount.rate_table', 'enhanced-pricing.profile.table-not-used']]);
});

test('a column needs the order to have a rate card row', function () {
    $profile = Profiles::set($this->profile, 'fees.2.enabled', true);
    $profile = Profiles::set($profile, 'fees.2.amount', $this->column);

    expect(fn () => ($this->price)($profile, ['service_rate' => 'distance', 'distance_meters' => 1000]))
        ->toThrow(PricingFailed::class, 'enhanced-pricing.pricing.row-from-other-table');
});

test('the checks name what a fee amount refers to', function (mixed $amount, array $expected) {
    $profile  = Profiles::set(Profiles::example(), 'fees.2.amount', $amount);
    $problems = (new ProfileValidator())->check($profile);

    expect(array_map(static fn ($problem) => [$problem->path, $problem->key], $problems))->toBe($expected);
})->with([
    'a number'         => [2500, []],
    'a value'          => [['value' => 2500], []],
    'a column'         => [['column' => 'second', 'rate_table' => 'rows'], []],
    'no table'         => [
        ['column' => 'second'],
        [['fees.2.amount.rate_table', 'enhanced-pricing.profile.missing-field']],
    ],
    'both'             => [
        ['value' => 1, 'column' => 'second', 'rate_table' => 'rows'],
        [['fees.2.amount', 'enhanced-pricing.profile.needs-exactly-one']],
    ],
    'an unknown table' => [
        ['column' => 'second', 'rate_table' => 'nope'],
        [['fees.2.amount.rate_table', 'enhanced-pricing.profile.unknown-reference']],
    ],
    'an unknown column' => [
        ['column' => 'nope', 'rate_table' => 'rows'],
        [['fees.2.amount.column', 'enhanced-pricing.profile.unknown-reference']],
    ],
    'a text column'    => [
        ['column' => 'code', 'rate_table' => 'rows'],
        [['fees.2.amount.column', 'enhanced-pricing.profile.column-wrong-type']],
    ],
]);

test('normalize keeps a plain number and reads { value } as one', function () {
    $profile = Profiles::set(Profiles::example(), 'fees.2.amount', ['value' => 2500]);
    $profile = Profiles::set($profile, 'subtotal_fees.1.amount', ['column' => 'first', 'rate_table' => 'rows']);

    $normalized = (new ProfileValidator())->normalize($profile);

    expect($normalized['fees'][2]['amount'])->toBe(2500)
        ->and($normalized['fees'][0]['amount'])->toBe(1600)
        ->and($normalized['subtotal_fees'][1]['amount'])->toBe(['column' => 'first', 'rate_table' => 'rows']);
});

test('overrides and dated changes take a number, a value or a column', function (mixed $value) {
    $path = 'fees.peak.amount';

    expect((new OverrideValidator())->check(Profiles::example(), [$path => $value]))->toBe([])
        ->and((new ChangeValidator())->check(Profiles::example(), [
            ['path' => $path, 'value' => $value, 'effective_from' => '2027-04-01'],
        ]))->toBe([]);
})->with([
    'a number' => [3000],
    'a value'  => [['value' => 3000]],
    'a column' => [['column' => 'third', 'rate_table' => 'rows']],
    'cleared'  => [null],
]);

test('an override to a column prices from the row, and one to a bad column is refused', function () {
    $profile = Profiles::set($this->profile, 'fees.2.enabled', true);

    $third = ['column' => 'third', 'rate_table' => 'rows'];
    $quote = ($this->price)($profile, ['overrides' => ['fees.peak.amount' => $third]]);
    $bad   = (new OverrideValidator())->check(
        Profiles::example(),
        ['fees.peak.amount' => ['column' => 'code', 'rate_table' => 'rows']],
    );

    expect(Orders::lines($quote)[1])->toBe(['PEAK', 'Peak hours fee', 1, 1200, 1200])
        ->and($bad)->not->toBe([]);
});

test('a dated change to a column takes effect on its date', function () {
    $profile = Profiles::set($this->profile, 'fees.2.enabled', true);
    $change  = ['path' => 'fees.peak.amount', 'value' => $this->column, 'effective_from' => '2027-04-01'];

    $before = ($this->price)($profile, ['changes' => [$change], 'pickup' => '2027-03-27 10:00']);
    $after  = ($this->price)($profile, ['changes' => [$change], 'pickup' => '2027-04-03 10:00']);

    expect(Orders::lines($before)[1][3])->toBe(2200)->and(Orders::lines($after)[1][3])->toBe(1800);
});
