<?php

use Fleetbase\EnhancedPricing\Admin\AdminRefused;
use Fleetbase\EnhancedPricing\Admin\Profiles;
use Fleetbase\EnhancedPricing\Tests\Support\Database;
use Fleetbase\EnhancedPricing\Tests\Support\Profiles as ExampleProfiles;
use Fleetbase\EnhancedPricing\Tests\Support\Stores;

// The admin page's profiles: the list, each version, and the editor's create and save, with every
// refusal shaped for the console.

beforeEach(function () {
    Database::fresh();
    Database::seed();
});

function adminProfiles(string $company = 'a'): Profiles
{
    return new Profiles(Database::company($company), static fn () => new DateTimeImmutable(Stores::NOW));
}

/**
 * @return array<string, mixed> the refusal's body, with its status under "status"
 */
function adminRefusal(Closure $call): array
{
    try {
        $call();
    } catch (AdminRefused $refused) {
        return ['status' => $refused->status] + $refused->body;
    }

    throw new LogicException('not refused');
}

test('the list shows each of the company\'s profiles with its current version and customers', function () {
    $profile = Stores::profile('a');
    Stores::for('a')->addVersion($profile, ExampleProfiles::example(), Database::user('a'));
    Stores::customer('a', $profile);
    Stores::profile('b');

    $list = adminProfiles()->list();

    expect($list)->toHaveCount(1)
        ->and($list[0])->toMatchArray([
            'key'             => 'main',
            'name'            => 'Main',
            'currency'        => 'USD',
            'distance_unit'   => 'km',
            'current_version' => 2,
            'customers'       => 1,
            'saved_by'        => ['uuid' => Database::user('a'), 'name' => 'User a'],
        ])
        ->and($list[0]['saved_at'])->toBeString();
});

test('a version shows its definition and every version of the profile', function () {
    $profile = Stores::profile('a');
    $changed = ExampleProfiles::set(ExampleProfiles::example(), 'products.0.name', 'Renamed');
    Stores::for('a')->addVersion($profile, $changed, Database::user('a'));

    $current = adminProfiles()->show('main');
    $first   = adminProfiles()->show('main', 1);

    expect($current['version']['number'])->toBe(2)
        ->and($current['version']['definition']['products'][0]['name'])->toBe('Renamed')
        ->and($current['version']['saved_by']['name'])->toBe('User a')
        ->and($first['version']['definition'])->toBe(ExampleProfiles::example())
        ->and($first['version']['saved_by'])->toBeNull()
        ->and(array_column($current['versions'], 'number'))->toBe([2, 1]);
});

test('another company\'s profile, or a missing version, is not found', function () {
    Stores::profile('b');
    Stores::profile('a', null, 'own');

    expect(adminRefusal(fn () => adminProfiles()->show('main')))
        ->toMatchArray(['status' => 404, 'refused' => 'not-found'])
        ->and(adminRefusal(fn () => adminProfiles()->show('own', 9)))->toMatchArray(['status' => 404])
        ->and(adminRefusal(fn () => adminProfiles()->save('main', ExampleProfiles::example(), 1, [], null)))
        ->toMatchArray(['status' => 404]);
});

test('creating a profile saves version 1', function () {
    $shown = adminProfiles()->create('new', 'New', ExampleProfiles::example(), Database::user('a'));

    expect($shown['key'])->toBe('new')
        ->and($shown['version']['number'])->toBe(1)
        ->and($shown['version']['saved_by']['name'])->toBe('User a');
});

test('an invalid profile is refused with every problem at its position', function () {
    $bad = ExampleProfiles::set(ExampleProfiles::example(), 'fees.0.amount', -1);
    $bad = ExampleProfiles::set($bad, 'products.1.code', '');

    $body = adminRefusal(fn () => adminProfiles()->create('new', 'New', $bad, null));

    expect($body['status'])->toBe(422)
        ->and($body['refused'])->toBe('invalid')
        ->and(array_column($body['problems'], 'path'))->toContain('fees.0.amount', 'products.1.code');
});

test('a key already used is refused', function () {
    Stores::profile('a');

    expect(adminRefusal(fn () => adminProfiles()->create('main', 'Again', ExampleProfiles::example(), null)))
        ->toMatchArray(['status' => 422, 'refused' => 'invalid']);
});

test('saving makes the next version', function () {
    Stores::profile('a');

    $shown = adminProfiles()->save('main', ExampleProfiles::example(), 1, [], Database::user('a'));

    expect($shown['current_version'])->toBe(2)
        ->and($shown['version']['saved_by']['name'])->toBe('User a');
});

test('a save built on an older version names who saved the newer one, and when', function () {
    $profile = Stores::profile('a');
    Stores::for('a')->addVersion($profile, ExampleProfiles::example(), Database::user('a'));

    $body = adminRefusal(fn () => adminProfiles()->save('main', ExampleProfiles::example(), 1, [], null));

    expect($body['status'])->toBe(409)
        ->and($body['refused'])->toBe('outdated')
        ->and($body['newer']['number'])->toBe(2)
        ->and($body['newer']['saved_by']['name'])->toBe('User a')
        ->and($body['newer']['saved_at'])->toBeString();
});

test('a save without a version it was built on replaces the newest', function () {
    $profile = Stores::profile('a');
    Stores::for('a')->addVersion($profile, ExampleProfiles::example());

    expect(adminProfiles()->save('main', ExampleProfiles::example(), null, [], null)['current_version'])->toBe(3);
});

test('a save that leaves stored entries stale lists them by name, and is saved once all are accepted', function () {
    $profile  = Stores::profile('a');
    $customer = Stores::customer('a', $profile, ['fees.heavy.minimum' => 500]);
    $card     = Stores::card('a', $customer, 'North');
    Stores::for('a')->saveRows($card, 'rows', [['values' => Stores::row('R1')]]);
    $without  = Stores::withoutFee('heavy');

    $body = adminRefusal(fn () => adminProfiles()->save('main', $without, 1, [], null));

    expect($body['status'])->toBe(409)
        ->and($body['refused'])->toBe('stale')
        ->and($body['stale'])->toHaveCount(1)
        ->and($body['stale'][0])
        ->toMatchArray(['kind' => 'customer', 'uuid' => $customer->uuid, 'label' => 'Customer a 1'])
        ->and($body['stale'][0]['problems'][0]['path'])->toBe('fees.heavy.minimum');

    $saved = adminProfiles()->save('main', $without, 1, [$customer->uuid], null);

    expect($saved['current_version'])->toBe(2);
});

test('accepting only some stale entries saves nothing and lists them again', function () {
    $profile = Stores::profile('a');
    $first   = Stores::customer('a', $profile, ['fees.heavy.minimum' => 500]);
    Stores::for('a')->saveCustomer(Database::customer('a', 2), $profile, ['fees.heavy.maximum' => 900]);

    $body = adminRefusal(fn () => adminProfiles()->save('main', Stores::withoutFee('heavy'), 1, [$first->uuid], null));

    expect($body['refused'])->toBe('stale')
        ->and($body['stale'])->toHaveCount(2)
        ->and(adminProfiles()->show('main')['current_version'])->toBe(1);
});

test('a stale row is named by its card and key', function () {
    $profile = Stores::profile('a');
    $card    = Stores::card('a', Stores::customer('a', $profile), 'North');
    Stores::for('a')->saveRows($card, 'rows', [['values' => Stores::row('R1', ['separate_price' => 300])]]);
    $changed = ExampleProfiles::set(ExampleProfiles::example(), 'rate_tables.0.columns.4.type', 'text');
    $changed = ExampleProfiles::remove($changed, 'entity_types.1');

    $body = adminRefusal(fn () => adminProfiles()->save('main', $changed, 1, [], null));

    expect($body['refused'])->toBe('stale')
        ->and($body['stale'][0])->toMatchArray(['kind' => 'row', 'label' => 'Customer a 1 · North · R1']);
});
