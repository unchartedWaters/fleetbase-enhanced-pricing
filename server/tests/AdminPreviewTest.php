<?php

use Fleetbase\EnhancedPricing\Admin\AdminRefused;
use Fleetbase\EnhancedPricing\Admin\Preview;
use Fleetbase\EnhancedPricing\Pricing\PricingEngine;
use Fleetbase\EnhancedPricing\Tests\Support\Database;
use Fleetbase\EnhancedPricing\Tests\Support\Orders;
use Fleetbase\EnhancedPricing\Tests\Support\Stores;

// The admin page's quote preview: a sample order priced by the real engine from what is stored
// for the customer, saving nothing.

beforeEach(function () {
    Database::fresh();
    Database::seed();
});

function adminPreview(string $company = 'a'): Preview
{
    return new Preview(Database::company($company), static fn () => new DateTimeImmutable(Stores::NOW));
}

function previewRefusal(Closure $call): array
{
    try {
        $call();
    } catch (AdminRefused $refused) {
        return ['status' => $refused->status] + $refused->body;
    }

    throw new LogicException('not refused');
}

/**
 * The worked example's Saturday order, as the console sends it.
 *
 * @return array<string, mixed>
 */
function previewInput(array $changes = []): array
{
    $entity = static fn (int $kg, ?string $type = null): array => [
        'type'        => $type,
        'weight'      => $kg,
        'weight_unit' => 'kg',
    ];
    $light  = array_fill(0, 5, $entity(5));

    return array_merge([
        'service_rate' => 'tiered',
        'pickup_at'    => '2027-03-06T11:00:00Z',
        'timezone'     => 'UTC',
        'attributes'   => ['extra' => true],
        'entities'     => [$entity(36), ...$light, $entity(5, 'separate')],
    ], $changes);
}

/**
 * @return array{customer: string, row: string}
 */
function previewCustomer(array $overrides = []): array
{
    $profile  = Stores::profile('a');
    $customer = Stores::customer('a', $profile, $overrides);
    $card     = Stores::card('a', $customer, 'Main');
    $rows     = Stores::for('a')->saveRows($card, 'rows', [['values' => Orders::ROW_1]]);

    return ['customer' => $customer->uuid, 'row' => $rows[0]->uuid];
}

test('a customer\'s preview prices the same lines as the engine', function () {
    $input  = previewInput(previewCustomer());
    $engine = (new PricingEngine())->price(Orders::request([
        'pickup'     => Orders::SATURDAY,
        'attributes' => ['extra' => true],
        'entities'   => [
            Orders::entity(36), Orders::entity(5), Orders::entity(5), Orders::entity(5),
            Orders::entity(5), Orders::entity(5), Orders::entity(5, 'separate'),
        ],
        'labels'     => ['customer' => 'Customer a 1', 'card' => 'Main', 'row' => 'R1'],
    ]));

    $preview = adminPreview()->price($input);

    expect($preview['quote']['lines'])->toBe($engine->toArray()['lines'])
        ->and($preview['quote']['total'])->toBe(19803)
        ->and($preview['failed'])->toBeNull();
});

test('a preview with no customer prices the profile\'s global values', function () {
    Stores::profile('a');

    $preview = adminPreview()->price(
        previewInput(['profile' => 'main', 'service_rate' => 'distance', 'distance_meters' => 5000]),
    );

    expect($preview['quote']['total'])->toBeGreaterThan(0);
});

test('a failure comes back with its message key', function () {
    $preview = adminPreview()->price(previewInput(previewCustomer() + ['entities' => []]));

    expect($preview['quote'])->toBeNull()
        ->and($preview['failed']['key'])->toBe('enhanced-pricing.pricing.no-entities');
});

test('a customer\'s overrides and live dated changes are used', function () {
    $ids = previewCustomer(['subtotal_fees.percent.percentage' => 2000]);
    Stores::for('a')->addDatedChange(Stores::profileOf('a'), 'subtotal_fees.flat_a.amount', 900, '2026-12-01');

    $lines = adminPreview()->price(previewInput($ids))['quote']['lines'];

    // 20% instead of 11% of the same $169.40 of lines.
    $amounts = array_column($lines, 'amount', 'product');
    expect($amounts['PERCENT'])->toBe(3388)
        ->and($amounts['FLAT_A'])->toBe(900);
});

test('a stale dated change is left out and listed', function () {
    $ids     = previewCustomer();
    $profile = Stores::profileOf('a');
    $store   = Stores::for('a');
    $store->addDatedChange($profile, 'fees.heavy.minimum', 100, '2026-12-01');
    $store->addVersion($profile, Stores::withoutFee('heavy'), null, 1, true);

    $preview = adminPreview()->price(previewInput($ids));

    expect($preview['skipped_changes'])->toBe([['path' => 'fees.heavy.minimum', 'effective_from' => '2026-12-01']])
        ->and($preview['quote'])->not->toBeNull();
});

test('a customer with stale overrides gets its problems, not a quote', function () {
    $ids = previewCustomer(['fees.heavy.minimum' => 500]);
    Stores::for('a')->addVersion(Stores::profileOf('a'), Stores::withoutFee('heavy'), null, 1, true);

    $preview = adminPreview()->price(previewInput($ids));

    expect($preview['quote'])->toBeNull()
        ->and($preview['stale'][0]['path'])->toBe('fees.heavy.minimum');
});

test('a row must be the customer\'s, of the service rate\'s table, and without problems', function () {
    $ids   = previewCustomer();
    $other = Stores::for('a')->saveCustomer(Database::customer('a', 2), Stores::profileOf('a'));
    $row   = Stores::for('a')->saveRows(Stores::card('a', $other, 'Other'), 'rows', [['values' => Orders::ROW_2]])[0];

    expect(previewRefusal(fn () => adminPreview()->price(previewInput(['row' => $row->uuid] + $ids))))
        ->toMatchArray(['status' => 422, 'refused' => 'invalid'])
        ->and(previewRefusal(fn () => adminPreview()->price(previewInput([
            'service_rate'    => 'distance',
            'distance_meters' => 5000,
        ] + $ids))))->toMatchArray(['status' => 422, 'refused' => 'invalid']);

    $row->problems = [['path' => 'values.first', 'key' => 'enhanced-pricing.profile.wrong-type', 'params' => []]];
    $row->save();

    $input = previewInput(['customer' => $other->uuid, 'row' => $row->uuid]);

    expect(previewRefusal(fn () => adminPreview()->price($input)))
        ->toMatchArray(['status' => 422, 'refused' => 'invalid']);
});

test('another company\'s customer, profile or row is not found', function () {
    $other = Stores::customer('b');

    expect(previewRefusal(fn () => adminPreview()->price(previewInput(['customer' => $other->uuid]))))
        ->toMatchArray(['status' => 404])
        ->and(previewRefusal(fn () => adminPreview()->price(previewInput(['profile' => 'main']))))
        ->toMatchArray(['status' => 404]);
});

test('a malformed preview request is refused', function (array $change) {
    Stores::profile('a');

    expect(previewRefusal(fn () => adminPreview()->price(previewInput(['profile' => 'main'] + $change))))
        ->toMatchArray(['status' => 422, 'refused' => 'invalid']);
})->with([
    'no service rate'     => [['service_rate' => null]],
    'a bad pickup time'   => [['pickup_at' => 'tomorrow-ish']],
    'a bad timezone'      => [['timezone' => 'Mars/Base']],
    'entities not a list' => [['entities' => 'many']],
    'too many entities'   => [['entities' => array_fill(0, 1001, ['weight' => 1])]],
    'a negative distance' => [['distance_meters' => -5]],
]);
