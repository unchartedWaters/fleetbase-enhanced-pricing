<?php

use Fleetbase\EnhancedPricing\Models\RateCardRow;
use Fleetbase\EnhancedPricing\Pricing\PricingFailed;
use Fleetbase\EnhancedPricing\Profile\ProfileValidator;
use Fleetbase\EnhancedPricing\Tests\Support\BillingProfiles;
use Fleetbase\EnhancedPricing\Tests\Support\Database;
use Fleetbase\EnhancedPricing\Tests\Support\Orders;
use Fleetbase\EnhancedPricing\Tests\Support\Profiles;
use Fleetbase\EnhancedPricing\Tests\Support\Stores;

// Billing codes. A service rate can take its line's billing code from a text column of the rate
// card row (a route), so each route bills under its own code. Every other line, and a service rate
// without the field, bills under its product's code. Fees still match lines by product.

// The engine.

test('a line without a billing code column bills under its product code', function () {
    $quote = Orders::price(['entities' => [Orders::entity(5), Orders::entity(5, 'separate')]]);

    expect(array_map(static fn ($line): array => [$line->product, $line->billingCode], $quote->lines))
        ->toBe([['TIERED', 'TIERED'], ['SEPARATE', 'SEPARATE'], ['PERCENT', 'PERCENT']])
        ->and($quote->lines[0]->toArray()['billing_code'])->toBe('TIERED');
});

test('the service fee bills under the route row\'s billing code', function () {
    $quote = Orders::price([
        'profile'  => BillingProfiles::profile(),
        'row'      => Orders::ROW_1 + ['billing_code' => '4010-NORTH'],
        'entities' => [Orders::entity(5), Orders::entity(5), Orders::entity(5, 'separate')],
    ]);

    expect(BillingProfiles::lines($quote))->toBe([
        ['TIERED', '4010-NORTH', 'Tiered delivery', 5400],
        ['SEPARATE', 'SEPARATE', 'Separate entity type', 900],
    ])->and($quote->lines[0]->toArray())->toMatchArray(['product' => 'TIERED', 'billing_code' => '4010-NORTH']);
});

test('each route bills under its own code', function () {
    $profile = BillingProfiles::profile();
    $north   = Orders::price(['profile' => $profile, 'row' => Orders::ROW_1 + ['billing_code' => 'R-NORTH']]);
    $south   = Orders::price(['profile' => $profile, 'row' => Orders::ROW_2 + ['billing_code' => 'R-SOUTH']]);

    expect($north->lines[0]->billingCode)->toBe('R-NORTH')->and($south->lines[0]->billingCode)->toBe('R-SOUTH');
});

test('the billing code can be the route\'s own code column', function () {
    $profile = Profiles::set(BillingProfiles::profile(), 'service_rates.0.billing_code_column', 'code');

    $quote = Orders::price(['profile' => $profile]);

    expect($quote->lines[0]->billingCode)->toBe('R1');
});

test('a service fee raised to its minimum keeps the route\'s billing code', function () {
    $profile = Profiles::set(BillingProfiles::profile(), 'service_rates.0.minimum', 5000);

    $quote = Orders::price(['profile' => $profile, 'row' => Orders::ROW_1 + ['billing_code' => 'R-NORTH']]);

    expect(BillingProfiles::lines($quote))->toBe([['TIERED', 'R-NORTH', 'Tiered delivery', 5000]]);
});

test('fees and subtotal fees still match the service line by product and keep their own codes', function () {
    $quote = Orders::price([
        'profile' => BillingProfiles::profile(subtotalFees: true),
        'row'     => Orders::ROW_1 + ['billing_code' => 'R-NORTH'],
        'pickup'  => Orders::SATURDAY,
    ]);
    $plain = Orders::price(['pickup' => Orders::SATURDAY]);

    expect(array_map(static fn ($line): int => $line->amount(), $quote->lines))
        ->toBe(array_map(static fn ($line): int => $line->amount(), $plain->lines))
        ->and(array_map(static fn ($line): string => $line->billingCode, array_slice($quote->lines, 1)))
        ->toBe(array_map(static fn ($line): string => $line->product, array_slice($quote->lines, 1)))
        ->and($quote->lines[0]->billingCode)->toBe('R-NORTH');
});

test('a route with no billing code stops pricing', function (mixed $blank) {
    $row = Orders::ROW_1;
    if ($blank !== '__absent__') {
        $row['billing_code'] = $blank;
    }

    Orders::price(['profile' => BillingProfiles::profile(), 'row' => $row]);
})->with(['absent' => ['__absent__'], 'null' => [null], 'empty' => ['']])
    ->throws(PricingFailed::class, 'enhanced-pricing.pricing.missing-billing-code');

test('a billing code that is not a code stops pricing', function (string $code) {
    Orders::price(['profile' => BillingProfiles::profile(), 'row' => Orders::ROW_1 + ['billing_code' => $code]]);
})->with([
    'space'         => ['R NORTH'],
    'leading space' => [' R-NORTH'],
    'leading dash'  => ['-NORTH'],
    'too long'      => [str_repeat('A', 65)],
    'markup'        => ['<b>R</b>'],
])->throws(PricingFailed::class, 'enhanced-pricing.pricing.billing-code-invalid');

test('a billing code that is another product\'s code stops pricing, whatever its case', function (string $code) {
    Orders::price(['profile' => BillingProfiles::profile(), 'row' => Orders::ROW_1 + ['billing_code' => $code]]);
})->with(['same case' => ['PEAK'], 'other case' => ['peak']])
    ->throws(PricingFailed::class, 'enhanced-pricing.pricing.billing-code-is-product');

test('a product whose code differs from the rate\'s own only in case is still another product', function () {
    $twin    = ['code' => 'tiered', 'name' => 'Lowercase twin'];
    $profile = Profiles::append(BillingProfiles::profile(), 'products', $twin);

    Orders::price(['profile' => $profile, 'row' => Orders::ROW_1 + ['billing_code' => 'tiered']]);
})->throws(PricingFailed::class, 'enhanced-pricing.pricing.billing-code-is-product');

test('a billing code may be the service rate\'s own product code', function () {
    $row   = Orders::ROW_1 + ['billing_code' => 'TIERED'];
    $quote = Orders::price(['profile' => BillingProfiles::profile(), 'row' => $row]);

    expect($quote->lines[0]->billingCode)->toBe('TIERED');
});

test('lines with the same product, name and amount but different billing codes stay apart', function () {
    $book = new Fleetbase\EnhancedPricing\Pricing\LineBook('USD');
    $book->add([
        ['product' => 'P', 'billing_code' => 'A', 'name' => 'N', 'quantity' => 1, 'unit' => 100, 'source' => 's:1'],
        ['product' => 'P', 'billing_code' => 'B', 'name' => 'N', 'quantity' => 1, 'unit' => 100, 'source' => 's:2'],
        ['product' => 'P', 'billing_code' => 'A', 'name' => 'N', 'quantity' => 1, 'unit' => 100, 'source' => 's:3'],
    ]);

    expect(array_map(static fn ($line): array => [$line->billingCode, $line->quantity], $book->lines()))
        ->toBe([['A', 2], ['B', 1]])
        ->and($book->sum(['P']))->toBe(300)
        ->and($book->count(['P']))->toBe(2);
});

// The profile.

test('the billing code column defaults to none', function () {
    $normalized = (new ProfileValidator())->normalize(Profiles::example());

    expect($normalized['service_rates'][0]['billing_code_column'])->toBeNull()
        ->and((new ProfileValidator())->check(BillingProfiles::profile()))->toBe([]);
});

test('the billing code column must be a text column of the service rate\'s table', function (
    int $rate,
    mixed $value,
    string $key,
) {
    $path     = "service_rates.$rate.billing_code_column";
    $problems = (new ProfileValidator())->check(Profiles::set(BillingProfiles::profile(), $path, $value));

    expect(array_map(static fn ($problem): array => [$problem->path, $problem->key], $problems))
        ->toContain([$path, "enhanced-pricing.profile.$key"]);
})->with([
    'unknown column' => [0, 'nope', 'unknown-reference'],
    'money column'   => [0, 'first', 'column-wrong-type'],
    'not a key'      => [0, 'Billing Code', 'wrong-type'],
    'no rate table'  => [1, 'code', 'column-needs-rate-table'],
]);

// Rate card rows.

test('a route row is refused without a usable billing code', function (array $changes, string $key) {
    Database::fresh();
    Database::seed();
    $card = Stores::card('a', Stores::customer('a', Stores::profile('a', BillingProfiles::profile())));

    $rows = [['values' => Stores::row('R1', $changes)]];

    expect(Stores::refusal(fn () => Stores::for()->saveRows($card, 'rows', $rows)))->toBe([$key]);
})->with([
    'absent'        => [[], 'row-missing-value'],
    'empty'         => [['billing_code' => ''], 'row-missing-value'],
    'not a code'    => [['billing_code' => 'R NORTH'], 'row-billing-code-invalid'],
    'a product'     => [['billing_code' => 'peak'], 'row-billing-code-is-product'],
]);

test('a code that is one rate\'s own product is refused for another rate on the same column', function () {
    Database::fresh();
    Database::seed();
    $profile           = BillingProfiles::profile();
    $second            = $profile['service_rates'][0];
    $second['key']     = 'tiered_two';
    $second['product'] = 'FLAT_A';
    $profile           = Profiles::append($profile, 'service_rates', $second);
    $card              = Stores::card('a', Stores::customer('a', Stores::profile('a', $profile)));

    $rows = [['values' => Stores::row('R1', ['billing_code' => 'TIERED'])]];

    expect(Stores::refusal(fn () => Stores::for()->saveRows($card, 'rows', $rows)))
        ->toBe(['row-billing-code-is-product']);
});

test('a disabled service rate does not make rows need a billing code', function () {
    Database::fresh();
    Database::seed();
    $profile = Profiles::set(BillingProfiles::profile(), 'service_rates.0.enabled', false);
    $card    = Stores::card('a', Stores::customer('a', Stores::profile('a', $profile)));

    $rows = Stores::for()->saveRows($card, 'rows', [['values' => Stores::row()]]);

    expect($rows[0]->problems)->toBeNull();
});

test('a version adding a product with a route\'s billing code marks the route stale', function () {
    Database::fresh();
    Database::seed();
    $profile = Stores::profile('a', BillingProfiles::profile());
    $card    = Stores::card('a', Stores::customer('a', $profile));
    $values  = Stores::row('R1', ['billing_code' => 'R-NORTH']);
    $row     = Stores::for()->saveRows($card, 'rows', [['values' => $values]])[0];
    $next    = Profiles::append(BillingProfiles::profile(), 'products', ['code' => 'r-north', 'name' => 'North fee']);

    Stores::for()->addVersion($profile->refresh(), $next, acceptStale: true);

    expect(RateCardRow::query()->find($row->uuid)?->problems[0]['key'] ?? null)
        ->toBe('enhanced-pricing.store.row-billing-code-is-product');
});

test('a route row with a billing code saves', function () {
    Database::fresh();
    Database::seed();
    $card = Stores::card('a', Stores::customer('a', Stores::profile('a', BillingProfiles::profile())));

    $rows = Stores::for()->saveRows($card, 'rows', [['values' => Stores::row('R1', ['billing_code' => 'R-NORTH'])]]);

    expect($rows[0]->problems)->toBeNull();
});

test('a version that starts reading billing codes marks routes without one stale', function () {
    Database::fresh();
    Database::seed();
    $profile = Stores::profile();
    $card    = Stores::card('a', Stores::customer('a', $profile));
    $row     = Stores::for()->saveRows($card, 'rows', [['values' => Stores::row()]])[0];

    $next = BillingProfiles::profile();

    expect(Stores::refusal(fn () => Stores::for()->addVersion($profile, $next)))->toBe(['stale-entries']);

    Stores::for()->addVersion($profile->refresh(), BillingProfiles::profile(), acceptStale: true);

    expect(RateCardRow::query()->find($row->uuid)?->problems[0]['key'] ?? null)
        ->toBe('enhanced-pricing.store.row-missing-value');
});
