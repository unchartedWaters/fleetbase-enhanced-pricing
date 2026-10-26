<?php

use Fleetbase\EnhancedPricing\Admin\AdminRefused;
use Fleetbase\EnhancedPricing\Admin\Customers;
use Fleetbase\EnhancedPricing\Tests\Support\Database;
use Fleetbase\EnhancedPricing\Tests\Support\Stores;

// The admin page's customers: each customer of a profile, and for one customer its overrides next
// to the global values, its stale marks, and its cards and rows. Read-only: overrides and cards
// change only through a loader.

beforeEach(function () {
    Database::fresh();
    Database::seed();
});

function adminCustomers(string $company = 'a'): Customers
{
    return new Customers(Database::company($company), static fn () => new DateTimeImmutable(Stores::NOW));
}

test('the list shows each customer of the profile with its name and counts', function () {
    $profile = Stores::profile('a');
    $first   = Stores::customer('a', $profile, [
        'fees.heavy.minimum'               => 500,
        'subtotal_fees.percent.percentage' => 900,
    ]);
    Stores::card('a', $first, 'North');
    Stores::card('a', $first, 'South');
    Stores::for('a')->saveCustomer(Database::customer('a', 2), $profile);

    $page = adminCustomers()->list('main');

    expect($page['total'])->toBe(2)
        ->and($page['page'])->toBe(1)
        ->and($page['customers'][0])->toMatchArray([
            'uuid'      => $first->uuid,
            'name'      => 'Customer a 1',
            'overrides' => 2,
            'cards'     => 2,
            'problems'  => null,
        ])
        ->and($page['customers'][1])->toMatchArray(['name' => 'Customer a 2', 'overrides' => 0, 'cards' => 0]);
});

test('the list is paged and ordered by name', function () {
    $profile = Stores::profile('a');
    Stores::for('a')->saveCustomer(Database::customer('a', 2), $profile);
    Stores::customer('a', $profile);

    $page = adminCustomers()->list('main', 2, 1);

    expect($page)->toMatchArray(['total' => 2, 'page' => 2, 'per_page' => 1])
        ->and(array_column($page['customers'], 'name'))->toBe(['Customer a 2']);
});

test('a stale customer shows its problems in the list', function () {
    $profile = Stores::profile('a');
    Stores::customer('a', $profile, ['fees.heavy.minimum' => 500]);
    Stores::for('a')->addVersion($profile, Stores::withoutFee('heavy'), null, 1, true);

    $listed = adminCustomers()->list('main')['customers'][0];

    expect($listed['checked_version'])->toBe(2)
        ->and($listed['problems'][0]['path'])->toBe('fees.heavy.minimum');
});

test('one customer shows each override next to the global value and the change in effect', function () {
    $profile  = Stores::profile('a');
    $customer = Stores::customer('a', $profile, [
        'subtotal_fees.percent.percentage' => 900,
        'allowed_service_rates'            => ['tiered'],
    ]);
    $store    = Stores::for('a', '2026-11-02T12:00:00Z');
    $store->addDatedChange($profile, 'subtotal_fees.percent.percentage', 1300, '2026-12-01');

    $later = new Customers(Database::company('a'), static fn () => new DateTimeImmutable('2026-12-05T12:00:00Z'));
    $shown = $later->show($customer->uuid);

    expect($shown)->toMatchArray(['uuid' => $customer->uuid, 'name' => 'Customer a 1'])
        ->and($shown['profile']['key'])->toBe('main')
        ->and($shown['overrides'])->toBe([
            [
                'path'    => 'allowed_service_rates',
                'value'   => ['tiered'],
                'default' => null,
                'change'  => null,
            ],
            [
                'path'    => 'subtotal_fees.percent.percentage',
                'value'   => 900,
                'default' => 1100,
                'change'  => ['value' => 1300, 'effective_from' => '2026-12-01'],
            ],
        ]);
});

test('one customer shows its cards and their rows', function () {
    $profile  = Stores::profile('a');
    $customer = Stores::customer('a', $profile);
    $card     = Stores::card('a', $customer, 'North');
    Stores::for('a')->saveRows($card, 'rows', [['values' => Stores::row('R2')], ['values' => Stores::row('R1')]]);

    $cards = adminCustomers()->show($customer->uuid)['cards'];

    expect($cards)->toHaveCount(1)
        ->and($cards[0]['label'])->toBe('North')
        ->and(array_column($cards[0]['rows'], 'key_values'))->toBe([['R1'], ['R2']])
        ->and($cards[0]['rows'][0])
        ->toMatchArray(['table_key' => 'rows', 'values' => Stores::row('R1'), 'problems' => null]);
});

test('another company\'s profile or customer is not found', function () {
    $other = Stores::customer('b');
    Stores::profile('a', null, 'own');

    foreach ([fn () => adminCustomers()->list('main'), fn () => adminCustomers()->show($other->uuid)] as $call) {
        try {
            $call();
            $this->fail('not refused');
        } catch (AdminRefused $refused) {
            expect($refused->status)->toBe(404);
        }
    }
});
