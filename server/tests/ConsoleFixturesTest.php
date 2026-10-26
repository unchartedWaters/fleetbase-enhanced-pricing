<?php

use Fleetbase\EnhancedPricing\Admin\AdminRefused;
use Fleetbase\EnhancedPricing\Admin\Changes;
use Fleetbase\EnhancedPricing\Admin\Customers;
use Fleetbase\EnhancedPricing\Admin\Preview;
use Fleetbase\EnhancedPricing\Admin\ProfileForm;
use Fleetbase\EnhancedPricing\Admin\Profiles;
use Fleetbase\EnhancedPricing\Tests\Support\Database;
use Fleetbase\EnhancedPricing\Tests\Support\Orders;
use Fleetbase\EnhancedPricing\Tests\Support\Profiles as ExampleProfiles;
use Fleetbase\EnhancedPricing\Tests\Support\Stores;

// The console's tests render what the admin endpoints really answer. This test makes those
// answers from the repo's example profile with the real admin services, and fails when the
// committed copy no longer matches. Run with WRITE_CONSOLE_FIXTURES=1 to write it again. Each
// answer is the body its endpoint sends.

const CONSOLE_FIXTURES = __DIR__ . '/../../tests/helpers/console-fixtures.js';

beforeEach(function () {
    Database::fresh();
    Database::seed();
});

/**
 * @return array<string, mixed>
 */
function consoleRefusal(Closure $call): array
{
    try {
        $call();
    } catch (AdminRefused $refused) {
        return $refused->body;
    }

    throw new LogicException('not refused');
}

/**
 * Uuids are made at random and times are the clock's, so each is replaced with a stable stand-in:
 * the same uuid always gets the same one.
 */
function consoleStable(mixed $value, array &$uuids): mixed
{
    if (is_array($value)) {
        $stable = [];
        foreach ($value as $key => $item) {
            $stable[$key] = consoleStable($item, $uuids);
        }

        return $stable;
    }
    if (is_string($value) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $value)) {
        $uuids[$value] ??= sprintf('00000000-0000-4000-8000-%012d', count($uuids) + 1);

        return $uuids[$value];
    }
    if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}/', $value)) {
        return '2026-11-02T12:00:00+00:00';
    }

    return $value;
}

/**
 * @return array<string, mixed>
 */
function consoleFixtures(): array
{
    $clock    = static fn () => new DateTimeImmutable(Stores::NOW);
    $company  = Database::company('a');
    $user     = Database::user('a');
    $profiles = new Profiles($company, $clock);
    $profile  = Stores::profile('a');
    $renamed  = ExampleProfiles::set(ExampleProfiles::example(), 'products.0.name', 'Delivery');
    $profiles->save('main', $renamed, 1, [], $user);

    $customer = Stores::customer('a', $profile, [
        'subtotal_fees.percent.percentage' => 900,
        'fees.heavy.minimum'               => 500,
    ]);
    $card     = Stores::card('a', $customer, 'Main');
    $rows     = Stores::for('a')->saveRows($card, 'rows', [['values' => Orders::ROW_1], ['values' => Orders::ROW_2]]);
    Stores::for('a')->saveCustomer(Database::customer('a', 2), $profile);
    $changes = new Changes($company, $clock);
    $changes->add('main', 'subtotal_fees.percent.percentage', 1300, '2026-12-01', $user);

    $preview = (new Preview($company, $clock))->price([
        'customer'     => $customer->uuid,
        'row'          => $rows[0]->uuid,
        'service_rate' => 'tiered',
        'pickup_at'    => '2027-03-06T11:00:00Z',
        'timezone'     => 'UTC',
        'attributes'   => ['extra' => true],
        'entities'     => [
            ['weight' => 36, 'weight_unit' => 'kg'],
            ['weight' => 5, 'weight_unit' => 'kg'],
            ['weight' => 5, 'weight_unit' => 'kg', 'type' => 'separate'],
        ],
    ]);
    $failed  = (new Preview($company, $clock))->price([
        'customer'     => $customer->uuid,
        'row'          => $rows[0]->uuid,
        'service_rate' => 'tiered',
        'pickup_at'    => '2027-03-06T11:00:00Z',
        'entities'     => [],
    ]);

    $invalid  = ExampleProfiles::set(ExampleProfiles::example(), 'fees.0.minimum', -5);
    $invalid  = ExampleProfiles::set($invalid, 'products.1.code', 'bad code');
    $fixtures = [
        'form'      => (new ProfileForm())->spec(),
        'profiles'  => ['profiles' => $profiles->list()],
        'profile'   => $profiles->show('main'),
        'schedule'  => $changes->schedule('main'),
        'customers' => (new Customers($company, $clock))->list('main'),
        'customer'  => (new Customers($company, $clock))->show($customer->uuid),
        'preview'   => $preview,
        'failed'    => $failed,
        'refused'   => [
            'invalid'  => consoleRefusal(fn () => $profiles->save('main', $invalid, 2, [], $user)),
            'outdated' => consoleRefusal(fn () => $profiles->save('main', ExampleProfiles::example(), 1, [], $user)),
            'stale'    => consoleRefusal(fn () => $profiles->save('main', Stores::withoutFee('heavy'), 2, [], $user)),
        ],
    ];
    $uuids = [];

    return consoleStable($fixtures, $uuids);
}

function consoleFixturesSource(array $fixtures): string
{
    $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;
    $json  = json_encode($fixtures, $flags);

    return "// Made by server/tests/ConsoleFixturesTest.php from the admin services and the example\n"
        . "// profile. Don't edit: run the PHP tests with WRITE_CONSOLE_FIXTURES=1 to make it again.\n"
        . "export default $json;\n";
}

test('the console\'s fixtures are what the admin services answer', function () {
    $source = consoleFixturesSource(consoleFixtures());
    if (getenv('WRITE_CONSOLE_FIXTURES') === '1') {
        file_put_contents(CONSOLE_FIXTURES, $source);
    }

    expect(is_file(CONSOLE_FIXTURES))->toBeTrue('the fixtures are missing; write them')
        ->and((string) file_get_contents(CONSOLE_FIXTURES))->toBe($source);
});
