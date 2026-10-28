<?php

use Fleetbase\EnhancedPricing\Store\StoreRefused;
use Fleetbase\EnhancedPricing\Tests\Support\Database;
use Fleetbase\EnhancedPricing\Tests\Support\Profiles;
use Fleetbase\EnhancedPricing\Tests\Support\Stores;

// Unit slips caught on save (31 meaning 31% is 0.31%), and the customers a global value change
// won't reach because they have their own override. "Today" is 2026-11-02.

beforeEach(function () {
    Database::fresh();
    Database::seed();
});

test('an override ten or more times away from the profile value is refused unless accepted', function (
    mixed $value,
    array $keys,
) {
    $profile = Stores::profile();
    $path    = 'subtotal_fees.percent.percentage';

    expect(Stores::refusal(fn () => Stores::customer('a', $profile, [$path => $value])))->toBe($keys);
})->with([
    'percent typed as 11'      => [11, ['large-change']],
    'cents typed as dollars'   => [110, ['large-change']],
    'ten times larger'         => [11000, ['large-change']],
    'a normal raise'           => [1300, []],
    'zero turns it off'        => [0, []],
    'null when the rule skips' => [null, []],
]);

test('the refusal names the path and both values', function () {
    $profile = Stores::profile();

    try {
        Stores::customer('a', $profile, ['fees.extra_handling.amount' => 16]);
    } catch (StoreRefused $refused) {
        expect($refused->problems[0]->toArray())->toBe([
            'path'   => 'fees.extra_handling.amount',
            'key'    => 'enhanced-pricing.store.large-change',
            'params' => ['path' => 'fees.extra_handling.amount', 'from' => 1600, 'to' => 16],
        ]);

        return;
    }
    throw new RuntimeException('The override was saved.');
});

test('an accepted large override is saved, and saving it again is not refused', function () {
    $profile   = Stores::profile();
    $overrides = ['subtotal_fees.percent.percentage' => 11];
    $customer  = Stores::for()->saveCustomer(Database::customer('a'), $profile, $overrides, null, true);

    expect($customer->overrides)->toBe($overrides)
        ->and(Stores::refusal(fn () => Stores::for()->saveCustomer($customer->customer_uuid, $profile, $overrides)))
        ->toBe([]);
});

test('a dated change ten or more times away from the profile value is refused unless accepted', function () {
    $profile = Stores::profile();
    $path    = 'subtotal_fees.percent.percentage';

    expect(Stores::refusal(fn () => Stores::for()->addDatedChange($profile, $path, 13, '2026-12-01')))
        ->toBe(['large-change'])
        ->and(Stores::for()->addDatedChange($profile, $path, 13, '2026-12-01', null, true)->value)->toBe(13);
});

test('list values such as thresholds are not compared', function () {
    $profile = Stores::profile();

    expect(Stores::refusal(fn () => Stores::customer('a', $profile, [
        'fees.heavy.thresholds' => [['over' => 30, 'amount' => 4000]],
    ])))->toBe([]);
});

test('the customers with an override for a path are listed', function () {
    $profile = Stores::profile();
    $path    = 'subtotal_fees.percent.percentage';
    $store   = Stores::for();
    $with    = $store->saveCustomer(Database::customer('a', 1), $profile, [$path => 900]);
    $store->saveCustomer(Database::customer('a', 2), $profile, ['fees.heavy.maximum' => 900]);
    Stores::customer('b', null, [$path => 900]);

    $listed = array_map(static fn ($customer) => $customer->uuid, $store->customersWithOverride($profile, $path));

    expect($listed)->toBe([$with->uuid]);
});

test('amounts inside a value, such as a fixed rate or entered limits, are compared too', function () {
    $definition = Profiles::example();
    unset($definition['service_rates'][1]['per_meter']);
    $definition = Profiles::set($definition, 'service_rates.1.method', 'fixed_rate');
    $definition = Profiles::set($definition, 'service_rates.1.fixed_rate', ['amount' => ['value' => 4500]]);
    $profile    = Stores::profile('a', $definition);

    expect(Stores::refusal(fn () => Stores::customer('a', $profile, [
        'service_rates.distance.fixed_rate.amount' => ['value' => 45],
    ])))->toBe(['large-change'])
        ->and(Stores::refusal(fn () => Stores::customer('a', $profile, [
            'manual_charges.entered.entered' => ['minimum' => 8, 'maximum' => 15000],
        ])))->toBe(['large-change'])
        ->and(Stores::refusal(fn () => Stores::customer('a', $profile, [
            'manual_charges.entered.entered' => ['minimum' => 1000, 'maximum' => 20000],
        ])))->toBe([]);
});

test('a changed override is compared with the value it replaces', function () {
    $profile  = Stores::profile();
    $path     = 'subtotal_fees.percent.percentage';
    $store    = Stores::for();
    $customer = $store->saveCustomer(Database::customer('a'), $profile, [$path => 11], null, true);

    expect(Stores::refusal(fn () => $store->saveCustomer($customer->customer_uuid, $profile, [$path => 12])))
        ->toBe([])
        ->and(Stores::refusal(fn () => $store->saveCustomer($customer->customer_uuid, $profile, [$path => 1100])))
        ->toBe(['large-change']);
});

test('a dated change is compared with the change in effect before it', function () {
    $profile = Stores::profile();
    $path    = 'subtotal_fees.percent.percentage';
    $store   = Stores::for();
    $store->addDatedChange($profile, $path, 11000, '2026-12-01', null, true);

    expect(Stores::refusal(fn () => $store->addDatedChange($profile, $path, 11550, '2027-01-01')))->toBe([])
        ->and(Stores::refusal(fn () => $store->addDatedChange($profile, $path, 1100, '2027-02-01')))
        ->toBe(['large-change']);
});

test('an override that is not valid is refused for that before any large change', function () {
    $profile = Stores::profile();

    expect(Stores::refusal(fn () => Stores::customer('a', $profile, [
        'subtotal_fees.percent.percentage' => 11,
        'fees.extra_handling.amount'       => 'ten',
    ])))->toBe(['wrong-type']);
});
