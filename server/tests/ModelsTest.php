<?php

use Fleetbase\EnhancedPricing\Models\CrossCompanyReference;
use Fleetbase\EnhancedPricing\Models\Customer;
use Fleetbase\EnhancedPricing\Models\DatedChange;
use Fleetbase\EnhancedPricing\Models\ImmutableRecord;
use Fleetbase\EnhancedPricing\Models\ManualCharge;
use Fleetbase\EnhancedPricing\Models\Profile;
use Fleetbase\EnhancedPricing\Models\ProfileVersion;
use Fleetbase\EnhancedPricing\Models\RateCard;
use Fleetbase\EnhancedPricing\Models\RateCardRow;
use Fleetbase\EnhancedPricing\Tests\Support\Database;
use Fleetbase\EnhancedPricing\Tests\Support\Profiles;
use Fleetbase\EnhancedPricing\Tests\Support\Stores;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\QueryException;

// The models on their own: what the database and the model hooks refuse even when the store is
// bypassed.

beforeEach(function () {
    Database::fresh();
    Database::seed();
});

/**
 * One of everything for a company, through the store.
 *
 * @return array{profile: Profile, version: ProfileVersion, customer: Customer, card: RateCard, row: RateCardRow, change: DatedChange, charge: ManualCharge}
 */
function everything(string $company): array
{
    $store    = Stores::for($company);
    $profile  = Stores::profile($company);
    $customer = Stores::customer($company, $profile);
    $card     = $store->saveCard($customer, 'Main branch');
    $rows     = $store->saveRows($card, 'rows', [['values' => Stores::row()]]);
    $change   = $store->addDatedChange($profile, 'subtotal_fees.percent.percentage', 1300, '2026-12-01');
    $version  = $profile->currentVersion();
    $charge   = $store->addManualCharge(Database::order($company), $version, [
        'charge_key' => 'hourly',
        'product'    => 'HOURLY',
        'quantity'   => '1.5',
        'amount'     => 6300,
        'line_name'  => 'Hourly charge (1.5 h)',
    ]);

    return [
        'profile'  => $profile,
        'version'  => $version,
        'customer' => $customer,
        'card'     => $card,
        'row'      => $rows[0],
        'change'   => $change,
        'charge'   => $charge,
    ];
}

test('a company reads only its own records, for every model', function () {
    $a = everything('a');
    everything('b');

    foreach ($a as $record) {
        $class = $record::class;
        $uuids = $class::query()->forCompany(Database::company('a'))->pluck('uuid')->all();

        expect($uuids)->toBe([$record->uuid], "$class reads another company's rows")
            ->and($class::query()->count())->toBe(2);
    }
});

test('a saved version never changes and is never deleted', function () {
    $version = Stores::profile()->currentVersion();

    $version->meta = ['note' => 'changed'];
    expect(fn () => $version->save())->toThrow(ImmutableRecord::class)
        ->and(fn () => $version->delete())->toThrow(ImmutableRecord::class)
        ->and(ProfileVersion::query()->first()?->meta)->toBeNull();
});

test('a manual charge can only be removed, and only once', function () {
    $charge = everything('a')['charge'];

    $charge->amount = 1;
    expect(fn () => $charge->save())->toThrow(ImmutableRecord::class);

    $charge->refresh();
    $charge->removed_at = '2026-11-02 12:00:00';
    $charge->save();
    $charge->removed_at = '2026-11-03 12:00:00';

    expect(fn () => $charge->save())->toThrow(ImmutableRecord::class)
        ->and(fn () => $charge->delete())->toThrow(ImmutableRecord::class);
});

test('a dated change can only be cancelled', function () {
    $change = everything('a')['change'];

    $change->value = 1400;
    expect(fn () => $change->save())->toThrow(ImmutableRecord::class);

    $change->refresh();
    $change->live         = null;
    $change->cancelled_at = '2026-11-02 12:00:00';
    $change->save();
    $change->live = 1;

    expect(fn () => $change->save())->toThrow(ImmutableRecord::class)
        ->and(fn () => $change->delete())->toThrow(ImmutableRecord::class);
});

test('a record cannot point at another company\'s parent', function (string $class, string $column, string $parent) {
    $a = everything('a');
    $b = everything('b');

    $record            = $a[$class];
    $record->{$column} = $b[$parent]->uuid;

    expect(fn () => $record->save())->toThrow(CrossCompanyReference::class);
})->with([
    'customer to profile' => ['customer', 'profile_uuid', 'profile'],
    'card to customer'    => ['card', 'pricing_customer_uuid', 'customer'],
    'row to card'         => ['row', 'rate_card_uuid', 'card'],
    'change to profile'   => ['change', 'profile_uuid', 'profile'],
]);

test('a new record cannot point at another company\'s parent', function () {
    $b = everything('b');

    expect(fn () => ProfileVersion::query()->create([
        'company_uuid' => Database::company('a'),
        'profile_uuid' => $b['profile']->uuid,
        'version'      => 9,
        'definition'   => Profiles::example(),
    ]))->toThrow(CrossCompanyReference::class)
        ->and(fn () => ManualCharge::query()->create([
            'company_uuid'         => Database::company('a'),
            'order_uuid'           => Database::order('a'),
            'profile_version_uuid' => $b['version']->uuid,
            'charge_key'           => 'hourly',
            'product'              => 'HOURLY',
            'amount'               => 100,
            'line_name'            => 'Hourly charge',
            'added_at'             => '2026-11-02 12:00:00',
        ]))->toThrow(CrossCompanyReference::class);
});

test('a customer save that changes only meta never rechecks its overrides', function () {
    $store    = Stores::for();
    $profile  = Stores::profile();
    $customer = Stores::customer('a', $profile, ['fees.additional.amount' => 2000]);
    $removed  = Profiles::set(Profiles::example(), 'fees', [Profiles::example()['fees'][1]]);
    $store->addVersion($profile, $removed, acceptStale: true);

    $customer->refresh();
    $customer->meta = ['branch' => 'north'];
    $customer->save();

    expect($customer->refresh()->meta)->toBe(['branch' => 'north'])
        ->and($customer->problems)->not->toBeNull();
});

test('unique keys hold in the database', function (string $table, \Closure $duplicate) {
    $a = everything('a');

    expect(fn () => Capsule::table($table)->insert($duplicate($a)))->toThrow(QueryException::class);
})->with([
    'profile key per company' => ['enhanced_pricing_profiles', fn (array $a) => [
        'uuid'            => 'p2',
        'company_uuid'    => $a['profile']->company_uuid,
        'key'             => 'main',
        'name'            => 'Again',
        'currency'        => 'USD',
        'distance_unit'   => 'km',
        'current_version' => 1,
    ]],
    'version per profile' => ['enhanced_pricing_profile_versions', fn (array $a) => [
        'uuid'         => 'v2',
        'company_uuid' => $a['profile']->company_uuid,
        'profile_uuid' => $a['profile']->uuid,
        'version'      => 1,
        'definition'   => '{}',
    ]],
    'customer per company' => ['enhanced_pricing_customers', fn (array $a) => [
        'uuid'          => 'c2',
        'company_uuid'  => $a['customer']->company_uuid,
        'customer_uuid' => $a['customer']->customer_uuid,
        'profile_uuid'  => $a['profile']->uuid,
    ]],
    'card label per customer' => ['enhanced_pricing_rate_cards', fn (array $a) => [
        'uuid'                  => 'k2',
        'company_uuid'          => $a['card']->company_uuid,
        'pricing_customer_uuid' => $a['customer']->uuid,
        'label'                 => 'Main branch',
    ]],
    'row key per card and table' => ['enhanced_pricing_rate_card_rows', fn (array $a) => [
        'uuid'           => 'r2',
        'company_uuid'   => $a['row']->company_uuid,
        'rate_card_uuid' => $a['card']->uuid,
        'table_key'      => 'rows',
        'row_key'        => $a['row']->row_key,
    ]],
    'live change per path and date' => ['enhanced_pricing_dated_changes', fn (array $a) => [
        'uuid'           => 'd2',
        'company_uuid'   => $a['change']->company_uuid,
        'profile_uuid'   => $a['profile']->uuid,
        'path'           => 'subtotal_fees.percent.percentage',
        'value'          => '1400',
        'effective_from' => '2026-12-01',
        'added_at'       => '2026-11-02 12:00:00',
        'live'           => 1,
    ]],
]);

test('cancelled changes never block their date', function () {
    $a    = everything('a');
    $base = [
        'company_uuid'   => $a['change']->company_uuid,
        'profile_uuid'   => $a['profile']->uuid,
        'path'           => 'subtotal_fees.percent.percentage',
        'value'          => '1400',
        'effective_from' => '2026-12-01',
        'added_at'       => '2026-11-02 12:00:00',
        'live'           => null,
    ];
    Capsule::table('enhanced_pricing_dated_changes')->insert([$base + ['uuid' => 'd2'], $base + ['uuid' => 'd3']]);

    expect(DatedChange::query()->count())->toBe(3);
});

test('deleting a company row removes every pricing row of that company only', function () {
    $a = everything('a');
    everything('b');

    Capsule::table('companies')->where('uuid', Database::company('a'))->delete();

    foreach ($a as $record) {
        expect($record::query()->forCompany(Database::company('a'))->count())->toBe(0)
            ->and($record::query()->forCompany(Database::company('b'))->count())->toBe(1);
    }
});
