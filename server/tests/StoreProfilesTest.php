<?php

use Fleetbase\EnhancedPricing\Models\Customer;
use Fleetbase\EnhancedPricing\Models\DatedChange;
use Fleetbase\EnhancedPricing\Models\Profile;
use Fleetbase\EnhancedPricing\Models\RateCardRow;
use Fleetbase\EnhancedPricing\Store\StoreRefused;
use Fleetbase\EnhancedPricing\Tests\Support\Database;
use Fleetbase\EnhancedPricing\Tests\Support\Profiles;
use Fleetbase\EnhancedPricing\Tests\Support\Stores;

// Profiles and their versions: numbering, the fixed units, and the check of every stored
// override, dated change and rate card row when a new version is saved.

beforeEach(function () {
    Database::fresh();
    Database::seed();
});

test('a new profile starts at version 1, with its currency and distance unit', function () {
    $profile = Stores::profile();

    expect($profile->current_version)->toBe(1)
        ->and($profile->currency)->toBe('USD')
        ->and($profile->distance_unit)->toBe('km')
        ->and($profile->currentVersion()?->definition)->toBe(Profiles::example());
});

test('an invalid profile is refused and nothing is saved', function () {
    $keys = Stores::refusal(fn () => Stores::profile('a', Profiles::set(Profiles::example(), 'currency', 'usd')));

    expect($keys)->not->toBe([])
        ->and(Profile::query()->count())->toBe(0);
});

test('each new version takes the next number', function () {
    $profile = Stores::profile();
    $store   = Stores::for();

    $second = $store->addVersion($profile, Profiles::example());
    $third  = $store->addVersion($profile->refresh(), Profiles::example(), builtOn: 2);

    expect([$second->version, $third->version])->toBe([2, 3])
        ->and($profile->refresh()->current_version)->toBe(3);
});

test('a version built on an older one is refused', function () {
    $profile = Stores::profile();
    Stores::for()->addVersion($profile, Profiles::example());

    expect(Stores::refusal(fn () => Stores::for()->addVersion($profile->refresh(), Profiles::example(), builtOn: 1)))
        ->toBe(['version-outdated']);
});

test('the currency and distance unit are fixed for the life of a profile', function (string $field, string $value) {
    $profile = Stores::profile();

    expect(Stores::refusal(
        fn () => Stores::for()->addVersion($profile, Profiles::set(Profiles::example(), $field, $value)),
    ))->toBe(['fixed-unit-changed']);
})->with([
    'currency'      => ['currency', 'CAD'],
    'distance unit' => ['distance_unit', 'mi'],
]);

test('a unit cannot change under a stored override or live dated change', function (
    string $path,
    mixed $value,
    string $field,
    mixed $unit,
) {
    $profile = Stores::profile();
    Stores::customer('a', $profile, [$path => $value]);

    expect(Stores::refusal(
        fn () => Stores::for()->addVersion($profile, Profiles::set(Profiles::example(), $field, $unit)),
    ))->toBe(['unit-changed']);
})->with([
    'a parcel fee unit' => [
        'fees.parcel.thresholds',
        [['over' => 30, 'amount' => 50]],
        'fees.1.unit',
        'lb',
    ],
    'a manual charge quantity unit' => ['manual_charges.hourly.amount', 5000, 'manual_charges.0.quantity.unit', 'min'],
]);

test('the same unit change is allowed when nothing names the rule', function () {
    $profile = Stores::profile();
    Stores::customer('a', $profile, ['fees.additional.amount' => 2000]);

    $version = Stores::for()->addVersion($profile, Profiles::set(Profiles::example(), 'fees.1.unit', 'lb'));

    expect($version->version)->toBe(2);
});

test('a live dated change also holds its rule\'s unit', function () {
    $profile = Stores::profile();
    Stores::for()->addDatedChange($profile, 'manual_charges.hourly.amount', 4500, '2026-12-01');

    expect(Stores::refusal(
        fn () => Stores::for()->addVersion(
            $profile,
            Profiles::set(Profiles::example(), 'manual_charges.0.quantity.unit', 'min'),
        ),
    ))->toBe(['unit-changed']);
});

test('the undated value cannot change under a dated change already in effect', function () {
    $profile = Stores::profile();
    Stores::for()->addDatedChange($profile, 'subtotal_fees.percent.percentage', 1300, '2026-12-01');
    $changed = Profiles::set(Profiles::example(), 'subtotal_fees.0.percentage', 1250);
    $later   = Stores::for('a', '2026-12-05T12:00:00Z');

    expect(Stores::refusal(fn () => $later->addVersion($profile, $changed)))->toBe(['value-has-change-in-effect'])
        ->and(Stores::for()->addVersion($profile, $changed)->version)->toBe(2);
});

test('a version that leaves entries stale is refused with the list', function () {
    $profile  = Stores::profile();
    $customer = Stores::customer('a', $profile, ['fees.additional.amount' => 2000]);
    $change   = Stores::for()->addDatedChange($profile, 'fees.additional.amount', 1800, '2026-12-01');

    try {
        Stores::for()->addVersion($profile, Stores::withoutFee('additional'));
        $refused = null;
    } catch (StoreRefused $caught) {
        $refused = $caught;
    }

    expect($refused?->problems[0]->key)->toBe('enhanced-pricing.store.stale-entries')
        ->and(array_map(static fn (array $entry) => [$entry['kind'], $entry['uuid']], $refused?->stale ?? []))
        ->toEqualCanonicalizing([['customer', $customer->uuid], ['dated_change', $change->uuid]])
        ->and($profile->refresh()->current_version)->toBe(1);
});

test('a version accepted with stale entries records them', function () {
    $profile  = Stores::profile();
    $customer = Stores::customer('a', $profile, ['fees.additional.amount' => 2000]);
    $fine     = Stores::for()->saveCustomer(
        Database::customer('a', 2),
        $profile,
        ['fees.parcel.thresholds' => [['over' => 30, 'amount' => 50]]],
    );
    $change   = Stores::for()->addDatedChange($profile, 'fees.additional.amount', 1800, '2026-12-01');

    Stores::for()->addVersion($profile, Stores::withoutFee('additional'), acceptStale: true);

    expect($customer->refresh()->checked_version)->toBe(2)
        ->and($customer->problems[0]['key'] ?? null)->toBe('enhanced-pricing.profile.unknown-override')
        ->and($change->refresh()->checked_version)->toBe(2)
        ->and($change->problems)->not->toBeNull()
        ->and($change->live)->toBe(1)
        ->and($fine->refresh()->checked_version)->toBe(2)
        ->and($fine->problems)->toBeNull();
});

test('a row that no longer fits is stale', function () {
    $profile  = Stores::profile();
    $card     = Stores::card('a', Stores::customer('a', $profile));
    $row      = Stores::for()->saveRows($card, 'rows', [['values' => Stores::row()]])[0];
    $required = Profiles::set(Profiles::example(), 'rate_tables.0.columns.4.required', true);

    expect(Stores::refusal(fn () => Stores::for()->addVersion($profile, $required)))->toBe(['stale-entries']);

    Stores::for()->addVersion($profile->refresh(), $required, acceptStale: true);

    expect($row->refresh()->checked_version)->toBe(2)
        ->and($row->problems[0]['key'] ?? null)->toBe('enhanced-pricing.store.row-missing-value');
});

test('fixing a stale customer clears its mark', function () {
    $profile  = Stores::profile();
    $customer = Stores::customer('a', $profile, ['fees.additional.amount' => 2000]);
    Stores::for()->addVersion($profile, Stores::withoutFee('additional'), acceptStale: true);

    $fixed = Stores::for()->saveCustomer($customer->customer_uuid, $profile->refresh(), []);

    expect($fixed->problems)->toBeNull()
        ->and($fixed->checked_version)->toBe(2)
        ->and(Customer::query()->count())->toBe(1);
});

test('another company\'s profile is refused', function () {
    $profile = Stores::profile('b');

    expect(fn () => Stores::for('a')->addVersion($profile, Profiles::example()))
        ->toThrow(Fleetbase\EnhancedPricing\Models\CrossCompanyReference::class)
        ->and(DatedChange::query()->count() + RateCardRow::query()->count())->toBe(0);
});
