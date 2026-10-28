<?php

use Fleetbase\EnhancedPricing\Models\CrossCompanyReference;
use Fleetbase\EnhancedPricing\Models\DatedChange;
use Fleetbase\EnhancedPricing\Models\ImmutableRecord;
use Fleetbase\EnhancedPricing\Tests\Support\Database;
use Fleetbase\EnhancedPricing\Tests\Support\Profiles;
use Fleetbase\EnhancedPricing\Tests\Support\Stores;

// Storing, replacing and cancelling dated changes. "Today" is 2026-11-02 in the profile's
// scheduling timezone, UTC.

const RATE = 'subtotal_fees.percent.percentage';

beforeEach(function () {
    Database::fresh();
    Database::seed();
});

test('a change is stored live with who added it', function () {
    $change = Stores::for()->addDatedChange(Stores::profile(), RATE, 1300, '2026-12-01', 'user-1');

    expect($change->live)->toBe(1)
        ->and($change->value)->toBe(1300)
        ->and($change->effective_from)->toBe('2026-12-01')
        ->and($change->added_by_uuid)->toBe('user-1')
        ->and($change->checked_version)->toBe(1)
        ->and($change->problems)->toBeNull();
});

test('a new change must be dated after today and at most two years ahead', function (string $date, array $keys) {
    $profile = Stores::profile();

    expect(Stores::refusal(fn () => Stores::for()->addDatedChange($profile, RATE, 1300, $date)))->toBe($keys);
})->with([
    'today'          => ['2026-11-02', ['change-too-soon']],
    'yesterday'      => ['2026-11-01', ['change-too-soon']],
    'tomorrow'       => ['2026-11-03', []],
    'two years'      => ['2028-11-02', []],
    'past two years' => ['2028-11-03', ['change-too-far']],
]);

test('a change must name a value that can change by date, with the right type', function (
    string $path,
    mixed $value,
    string $key,
) {
    $profile = Stores::profile();

    $add = fn () => Stores::for()->addDatedChange($profile, $path, $value, '2026-12-01');

    expect(Stores::refusal($add))->toContain($key);
})->with([
    'unknown'    => ['fees.nothing.amount', 100, 'unknown-reference'],
    'not dated'  => ['fees.extra_handling.enabled', false, 'not-dated'],
    'wrong type' => [RATE, 'high', 'wrong-type'],
]);

test('sending the same change again does nothing, even once it is in effect', function () {
    $profile = Stores::profile();
    $first   = Stores::for()->addDatedChange($profile, RATE, 1300, '2026-12-01');

    $again = Stores::for('a', '2026-12-10T12:00:00Z')->addDatedChange($profile, RATE, 1300, '2026-12-01');

    expect($again->uuid)->toBe($first->uuid)->and(DatedChange::query()->count())->toBe(1);
});

test('a list value sent again with its keys in another order is the same change', function () {
    $profile = Stores::profile();
    $path    = 'fees.heavy.thresholds';
    $first   = Stores::for()->addDatedChange($profile, $path, [['over' => 30, 'amount' => 50]], '2026-12-01');

    $again = Stores::for('a', '2026-12-10T12:00:00Z')
        ->addDatedChange($profile, $path, [['amount' => 50, 'over' => 30]], '2026-12-01');

    expect($again->uuid)->toBe($first->uuid)->and(DatedChange::query()->count())->toBe(1);
});

test('a cancelled change keeps who added and cancelled it', function () {
    $change = Stores::for()->addDatedChange(Stores::profile(), RATE, 1300, '2026-12-01', 'user-1');
    Stores::for()->cancelDatedChange($change, 'user-2');

    $changes = ['added_by_uuid' => 'x', 'cancelled_by_uuid' => 'x', 'cancelled_at' => '2020-01-01 00:00:00'];
    foreach ($changes as $field => $value) {
        expect(fn () => $change->refresh()->update([$field => $value]))->toThrow(ImmutableRecord::class);
    }
    expect($change->refresh()->cancelled_by_uuid)->toBe('user-2')->and($change->added_by_uuid)->toBe('user-1');
});

test('a different value replaces a change not yet in effect', function () {
    $profile = Stores::profile();
    $first   = Stores::for()->addDatedChange($profile, RATE, 1300, '2026-12-01');

    $second = Stores::for()->addDatedChange($profile, RATE, 1400, '2026-12-01', 'user-2');

    expect($second->uuid)->not->toBe($first->uuid)
        ->and($second->value)->toBe(1400)
        ->and($first->refresh()->live)->toBeNull()
        ->and($first->cancelled_by_uuid)->toBe('user-2');
});

test('a different value for a change in effect is refused', function () {
    $profile = Stores::profile();
    Stores::for()->addDatedChange($profile, RATE, 1300, '2026-12-01');

    expect(Stores::refusal(
        fn () => Stores::for('a', '2026-12-01T12:00:00Z')->addDatedChange($profile, RATE, 1400, '2026-12-01'),
    ))->toBe(['change-in-effect']);
});

test('a cancelled change is reported, never added again', function () {
    $profile = Stores::profile();
    $change  = Stores::for()->addDatedChange($profile, RATE, 1300, '2026-12-01');
    Stores::for()->cancelDatedChange($change, 'user-1');

    expect(Stores::refusal(fn () => Stores::for()->addDatedChange($profile, RATE, 1300, '2026-12-01')))
        ->toBe(['change-was-cancelled'])
        ->and(DatedChange::query()->whereNotNull('live')->count())->toBe(0);
});

test('a change can be cancelled until the day before its date', function () {
    $profile = Stores::profile();
    $change  = Stores::for()->addDatedChange($profile, RATE, 1300, '2026-12-01');

    expect(Stores::refusal(fn () => Stores::for('a', '2026-12-01T00:30:00Z')->cancelDatedChange($change)))
        ->toBe(['change-in-effect']);

    $cancelled = Stores::for('a', '2026-11-30T23:30:00Z')->cancelDatedChange($change, 'user-1');
    expect($cancelled->live)->toBeNull()
        ->and($cancelled->cancelled_by_uuid)->toBe('user-1')
        ->and($cancelled->cancelled_at)->not->toBeNull();
});

test('today is judged in the profile\'s scheduling timezone', function () {
    $toronto = Profiles::set(Profiles::example(), 'scheduling_timezone', 'America/Toronto');
    $profile = Stores::profile('a', $toronto);

    // 02:00 UTC on 3 November is still 2 November in Toronto, so 3 November is tomorrow there.
    $change = Stores::for('a', '2026-11-03T02:00:00Z')->addDatedChange($profile, RATE, 1300, '2026-11-03');

    expect($change->effective_from)->toBe('2026-11-03');
});

test('an existing stale change does not block new ones', function () {
    $profile = Stores::profile();
    Stores::for()->addDatedChange($profile, 'fees.extra_handling.amount', 1800, '2026-12-01');
    Stores::for()->addVersion($profile, Stores::withoutFee('extra_handling'), acceptStale: true);

    $change = Stores::for()->addDatedChange($profile->refresh(), RATE, 1300, '2026-12-01');

    expect($change->checked_version)->toBe(2)->and($change->problems)->toBeNull();
});

test('another company\'s profile or change is refused', function () {
    $profile = Stores::profile('b');
    $change  = Stores::for('b')->addDatedChange($profile, RATE, 1300, '2026-12-01');

    expect(fn () => Stores::for('a')->addDatedChange($profile, RATE, 1300, '2026-12-02'))
        ->toThrow(CrossCompanyReference::class)
        ->and(fn () => Stores::for('a')->cancelDatedChange($change))
        ->toThrow(CrossCompanyReference::class)
        ->and($change->refresh()->live)->toBe(1);
});
