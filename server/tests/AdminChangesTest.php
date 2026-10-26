<?php

use Fleetbase\EnhancedPricing\Admin\AdminRefused;
use Fleetbase\EnhancedPricing\Admin\Changes;
use Fleetbase\EnhancedPricing\Tests\Support\Database;
use Fleetbase\EnhancedPricing\Tests\Support\Profiles as ExampleProfiles;
use Fleetbase\EnhancedPricing\Tests\Support\Stores;

// The admin page's dated changes: each subtotal fee's value today and its upcoming changes, the
// customers a change won't reach, scheduling and cancelling a change to a subtotal fee's global
// value (plan, Endpoints), and every stored change listed read-only.

beforeEach(function () {
    Database::fresh();
    Database::seed();
});

function adminChanges(string $now = Stores::NOW, string $company = 'a'): Changes
{
    return new Changes(Database::company($company), static fn () => new DateTimeImmutable($now));
}

function changeRefusal(Closure $call): array
{
    try {
        $call();
    } catch (AdminRefused $refused) {
        return ['status' => $refused->status] + $refused->body;
    }

    throw new LogicException('not refused');
}

test('each subtotal fee shows its value today, from the profile or a change in effect', function () {
    $profile = Stores::profile('a');
    Stores::for('a')->addDatedChange($profile, 'subtotal_fees.percent.percentage', 1300, '2026-12-01');

    $before = adminChanges()->schedule('main');
    $after  = adminChanges('2026-12-02T12:00:00Z')->schedule('main');

    expect(array_column($before['subtotal_fees'], 'key'))->toBe(['percent', 'flat_a', 'flat_b'])
        ->and($before['today'])->toBe('2026-11-02')
        ->and($before['subtotal_fees'][0])->toMatchArray([
            'path'          => 'subtotal_fees.percent.percentage',
            'mode'          => 'percentage',
            'profile_value' => 1100,
            'current'       => ['value' => 1100, 'effective_from' => null],
        ])
        ->and($before['subtotal_fees'][0]['upcoming'][0])
        ->toMatchArray(['value' => 1300, 'effective_from' => '2026-12-01'])
        ->and($before['subtotal_fees'][1]['path'])->toBe('subtotal_fees.flat_a.amount')
        ->and($after['subtotal_fees'][0]['current'])->toBe(['value' => 1300, 'effective_from' => '2026-12-01'])
        ->and($after['subtotal_fees'][0]['upcoming'])->toBe([]);
});

test('each subtotal fee names the customers whose own override a change won\'t reach', function () {
    $profile = Stores::profile('a');
    Stores::customer('a', $profile, ['subtotal_fees.percent.percentage' => 900]);
    Stores::for('a')->saveCustomer(Database::customer('a', 2), $profile);

    $fee = adminChanges()->schedule('main')['subtotal_fees'][0];

    expect($fee['overridden_by'])->toHaveCount(1)
        ->and($fee['overridden_by'][0])->toMatchArray(['name' => 'Customer a 1', 'value' => 900]);
});

test('scheduling a change to a subtotal fee\'s percentage saves it and shows it upcoming', function () {
    Stores::profile('a');

    $added = adminChanges()->add('main', 'subtotal_fees.percent.percentage', 1300, '2026-12-01', Database::user('a'));

    expect($added)->toMatchArray(['path' => 'subtotal_fees.percent.percentage', 'value' => 1300, 'state' => 'upcoming'])
        ->and($added['added_by']['name'])->toBe('User a')
        ->and(adminChanges()->schedule('main')['subtotal_fees'][0]['upcoming'])->toHaveCount(1);
});

test('only a subtotal fee\'s percentage or amount can be scheduled here', function (string $path) {
    Stores::profile('a');

    expect(changeRefusal(fn () => adminChanges()->add('main', $path, 100, '2026-12-01', null)))
        ->toMatchArray(['status' => 422, 'refused' => 'invalid']);
})->with([
    'a fee'                  => ['fees.heavy.minimum'],
    'a subtotal fee minimum' => ['subtotal_fees.percent.minimum'],
    'an unknown fee'         => ['subtotal_fees.nope.amount'],
    'a service rate'         => ['service_rates.distance.per_meter.rate'],
]);

test('a change the store refuses comes back with its problems', function () {
    Stores::profile('a');

    $body = changeRefusal(
        fn () => adminChanges()->add('main', 'subtotal_fees.percent.percentage', 1300, '2026-11-02', null),
    );

    expect($body['status'])->toBe(422)
        ->and($body['problems'][0]['key'])->toBe('enhanced-pricing.store.change-too-soon');
});

test('cancelling an upcoming change marks it cancelled', function () {
    $profile = Stores::profile('a');
    $change  = Stores::for('a')->addDatedChange($profile, 'subtotal_fees.percent.percentage', 1300, '2026-12-01');

    $cancelled = adminChanges()->cancel($change->uuid, Database::user('a'));

    expect($cancelled['state'])->toBe('cancelled')
        ->and($cancelled['cancelled_by']['name'])->toBe('User a')
        ->and(adminChanges()->schedule('main')['subtotal_fees'][0]['upcoming'])->toBe([]);
});

test('a change to another value, or another company\'s change, can\'t be cancelled here', function () {
    $profile = Stores::profile('a');
    $fee     = Stores::for('a')->addDatedChange($profile, 'fees.heavy.minimum', 100, '2026-12-01');
    $other   = Stores::for('b')
        ->addDatedChange(Stores::profile('b'), 'subtotal_fees.percent.percentage', 1300, '2026-12-01');

    expect(changeRefusal(fn () => adminChanges()->cancel($fee->uuid, null)))->toMatchArray(['status' => 422])
        ->and(changeRefusal(fn () => adminChanges()->cancel($other->uuid, null)))->toMatchArray(['status' => 404]);
});

test('every stored change is listed with its state', function () {
    $profile = Stores::profile('a');
    $store   = Stores::for('a');
    $store->addDatedChange($profile, 'fees.heavy.minimum', 100, '2026-12-01');
    $cancelled = $store->addDatedChange($profile, 'subtotal_fees.percent.percentage', 1300, '2026-12-01');
    $store->cancelDatedChange($cancelled);
    $store->addDatedChange($profile, 'subtotal_fees.flat_a.amount', 700, '2026-12-01');
    $store->addVersion($profile, ExampleProfiles::remove(ExampleProfiles::example(), 'subtotal_fees.1'), null, 1, true);

    $states = [];
    foreach (adminChanges()->schedule('main')['changes'] as $change) {
        $states[$change['path']] = $change['state'];
    }

    expect($states)->toBe([
        'fees.heavy.minimum'               => 'upcoming',
        'subtotal_fees.flat_a.amount'      => 'stale',
        'subtotal_fees.percent.percentage' => 'cancelled',
    ]);
});

test('a change\'s times are ISO times in UTC, as the console reads them', function () {
    $profile = Stores::profile('a');
    $change  = Stores::for('a')->addDatedChange($profile, 'subtotal_fees.percent.percentage', 1300, '2026-12-01');

    $cancelled = adminChanges()->cancel($change->uuid, Database::user('a'));

    expect($cancelled['added_at'])->toBe('2026-11-02T12:00:00+00:00')
        ->and($cancelled['cancelled_at'])->toBe('2026-11-02T12:00:00+00:00')
        ->and(adminChanges()->schedule('main')['changes'][0]['added_at'])->toBe('2026-11-02T12:00:00+00:00');
});
