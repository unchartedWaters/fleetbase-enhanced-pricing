<?php

use Fleetbase\EnhancedPricing\Models\Customer;
use Fleetbase\EnhancedPricing\Models\RateCardRow;
use Fleetbase\EnhancedPricing\Pricing\ServiceFee;
use Fleetbase\EnhancedPricing\Store\RowCheck;
use Fleetbase\EnhancedPricing\Tests\Support\Database;
use Fleetbase\EnhancedPricing\Tests\Support\Profiles;
use Fleetbase\EnhancedPricing\Tests\Support\Stores;
use Illuminate\Database\Capsule\Manager as Capsule;

// Customers, their overrides, rate cards and rows.

beforeEach(function () {
    Database::fresh();
    Database::seed();
});

test('a customer must be a customer contact of the company', function (\Closure $contact) {
    $profile = Stores::profile();

    expect(Stores::refusal(fn () => Stores::for()->saveCustomer($contact(), $profile)))->toBe(['not-a-customer'])
        ->and(Customer::query()->count())->toBe(0);
})->with([
    'another company\'s customer' => [fn () => Database::customer('b')],
    'a contact that isn\'t a customer' => [fn () => Database::contact('a')],
    'an unknown contact' => [fn () => Database::customer('a', 9)],
    'a deleted customer' => [function () {
        Capsule::table('contacts')->where('uuid', Database::customer('a'))->update(['deleted_at' => '2026-01-01 00:00:00']);

        return Database::customer('a');
    }],
]);

test('overrides must name values the profile defines, with the right type', function (array $overrides, string $key) {
    $profile = Stores::profile();

    expect(Stores::refusal(fn () => Stores::customer('a', $profile, $overrides)))->toContain($key)
        ->and(Customer::query()->count())->toBe(0);
})->with([
    'unknown value' => [['fees.nothing.amount' => 100], 'unknown-override'],
    'wrong type'    => [['fees.additional.amount' => 'ten'], 'wrong-type'],
]);

test('overrides sent replace the stored ones, absent keeps them and empty clears them', function () {
    $profile = Stores::profile();
    $store   = Stores::for();
    $uuid    = Database::customer('a');

    $store->saveCustomer($uuid, $profile, ['fees.additional.amount' => 2000]);
    $kept = $store->saveCustomer($uuid, $profile, null, ['branch' => 'north']);
    expect($kept->overrides)->toBe(['fees.additional.amount' => 2000])
        ->and($kept->meta)->toBe(['branch' => 'north']);

    $replaced = $store->saveCustomer($uuid, $profile, ['fees.parcel.thresholds' => [['over' => 30, 'amount' => 50]]]);
    expect($replaced->overrides)->toBe(['fees.parcel.thresholds' => [['over' => 30, 'amount' => 50]]]);

    expect($store->saveCustomer($uuid, $profile, [])->overrides)->toBe([])
        ->and(Customer::query()->count())->toBe(1);
});

test('a customer with cards cannot move to another profile', function () {
    $profile = Stores::profile();
    $other   = Stores::profile('a', null, 'other');
    Stores::card('a', Stores::customer('a', $profile));

    expect(Stores::refusal(fn () => Stores::for()->saveCustomer(Database::customer('a'), $other)))
        ->toBe(['customer-has-cards']);
});

test('meta is at most 4 KB', function () {
    $profile = Stores::profile();

    expect(Stores::refusal(
        fn () => Stores::for()->saveCustomer(Database::customer('a'), $profile, null, ['note' => str_repeat('x', 4100)]),
    ))->toBe(['meta-too-large']);
});

test('rows are checked against the rate table', function (array $row, string $key, string $table = 'rows') {
    $card = Stores::card();

    expect(Stores::refusal(fn () => Stores::for()->saveRows($card, $table, [['values' => $row]])))->toBe([$key]);
})->with([
    'unknown table'     => [Stores::row(), 'row-unknown-table', 'nothing'],
    'unknown column'    => [Stores::row('R1', ['colour' => 'red']), 'row-unknown-column'],
    'wrong type'        => [Stores::row('R1', ['first' => '10.00']), 'row-wrong-type'],
    'negative money'    => [Stores::row('R1', ['first' => -1]), 'row-wrong-type'],
    'missing required'  => [['code' => 'R1', 'first' => 1000, 'second' => 900], 'row-missing-value'],
    'blank key column'  => [Stores::row(''), 'row-missing-value'],
    'text too long'     => [Stores::row(str_repeat('x', 256)), 'row-wrong-type'],
]);

test('a blank optional column is accepted', function (mixed $blank) {
    $card = Stores::card();

    $rows = Stores::for()->saveRows($card, 'rows', [['values' => Stores::row('R1', ['separate_price' => $blank])]]);

    expect($rows)->toHaveCount(1)->and($rows[0]->problems)->toBeNull()->and($rows[0]->checked_version)->toBe(1);
})->with(['null' => [null], 'empty' => ['']]);

test('one bad row stops the whole batch and nothing is written', function () {
    $card = Stores::card();

    $keys = Stores::refusal(fn () => Stores::for()->saveRows($card, 'rows', [
        ['values' => Stores::row('R1')],
        ['values' => Stores::row('R2', ['first' => 'x'])],
    ]));

    expect($keys)->toBe(['row-wrong-type'])->and(RateCardRow::query()->count())->toBe(0);
});

test('a key sent twice in one call is refused', function () {
    $card = Stores::card();

    expect(Stores::refusal(fn () => Stores::for()->saveRows($card, 'rows', [
        ['values' => Stores::row('R1')],
        ['values' => Stores::row('R1', ['first' => 1100])],
    ])))->toBe(['duplicate-row']);
});

test('keys of several columns cannot collide', function () {
    $profile = Profiles::set(Profiles::example(), 'rate_tables.0.columns', [
        ['key' => 'from', 'type' => 'text', 'required' => true],
        ['key' => 'to', 'type' => 'text', 'required' => true],
        ['key' => 'first', 'type' => 'money', 'required' => true],
        ['key' => 'second', 'type' => 'money', 'required' => true],
        ['key' => 'third', 'type' => 'money', 'required' => true],
        ['key' => 'separate_price', 'type' => 'money', 'required' => false],
        ['key' => 'code', 'type' => 'text', 'required' => true],
    ]);
    $profile = Profiles::set($profile, 'rate_tables.0.row_key', ['from', 'to']);
    $card    = Stores::card('a', Stores::customer('a', Stores::profile('a', $profile)));
    $prices  = ['first' => 1, 'second' => 1, 'third' => 1, 'code' => 'x'];

    $rows = Stores::for()->saveRows($card, 'rows', [
        ['values' => ['from' => 'a', 'to' => 'b c'] + $prices],
        ['values' => ['from' => 'a b', 'to' => 'c'] + $prices],
    ]);

    expect($rows[0]->row_key)->not->toBe($rows[1]->row_key)
        ->and($rows[0]->key_values)->toBe(['a', 'b c']);
});

test('a number key is the same key however it is written', function () {
    $profile = Profiles::set(Profiles::example(), 'rate_tables.0.columns.5', ['key' => 'zone', 'type' => 'number', 'required' => true]);
    $profile = Profiles::set($profile, 'rate_tables.0.row_key', ['zone']);
    $card    = Stores::card('a', Stores::customer('a', Stores::profile('a', $profile)));

    $first  = Stores::for()->saveRows($card, 'rows', [['values' => Stores::row('R1', ['zone' => '1.50'])]]);
    $second = Stores::for()->saveRows($card, 'rows', [['values' => Stores::row('R2', ['zone' => 1.5])]]);

    expect($second[0]->uuid)->toBe($first[0]->uuid)
        ->and($second[0]->values['code'])->toBe('R2')
        ->and(RateCardRow::query()->count())->toBe(1);
});

test('a rerun updates a row without changing its uuid or key', function () {
    $card  = Stores::card();
    $first = Stores::for()->saveRows($card, 'rows', [['values' => Stores::row('R1')]])[0];

    $again = Stores::for()->saveRows($card, 'rows', [['values' => Stores::row('R1', ['first' => 1500]), 'meta' => ['n' => 1]]])[0];

    expect($again->uuid)->toBe($first->uuid)
        ->and($again->row_key)->toBe($first->row_key)
        ->and($again->values['first'])->toBe(1500)
        ->and($again->meta)->toBe(['n' => 1]);
});

test('a card holds at most 2,000 rows, and a call at most 5,000', function () {
    $card = Stores::card();
    $rows = array_map(static fn (int $n) => ['values' => Stores::row("R$n")], range(1, 2001));

    expect(Stores::refusal(fn () => Stores::for()->saveRows($card, 'rows', $rows)))->toBe(['too-many-rows']);
});

test('the row check agrees with the engine on every column type', function (string $type, mixed $value) {
    $fits   = (new ReflectionMethod(ServiceFee::class, 'valueFits'))->invoke(null, $type, $value);
    $column = ['key' => 'c', 'type' => $type, 'required' => true];

    expect(RowCheck::valueFits($column, $value))->toBe($fits);
})->with(function () {
    $values = [0, 1, -1, 100000, 100001, 100000000, 100000001, 1.5, '1.5', '1.50', '-1', 'x', '', str_repeat('y', 255), str_repeat('y', 256), true, null, [1]];
    foreach (['text', 'money', 'integer', 'number'] as $type) {
        foreach ($values as $index => $value) {
            yield "$type #$index" => [$type, $value];
        }
    }
});

test('another company\'s card or customer is refused', function () {
    $card = Stores::card('b');

    expect(fn () => Stores::for('a')->saveRows($card, 'rows', [['values' => Stores::row()]]))
        ->toThrow(Fleetbase\EnhancedPricing\Models\CrossCompanyReference::class)
        ->and(fn () => Stores::for('a')->saveCard($card->customer()->firstOrFail(), 'Other'))
        ->toThrow(Fleetbase\EnhancedPricing\Models\CrossCompanyReference::class)
        ->and(fn () => Stores::for('a')->saveCustomer(Database::customer('a'), Stores::profile('b', null, 'b2')))
        ->toThrow(Fleetbase\EnhancedPricing\Models\CrossCompanyReference::class);
});
