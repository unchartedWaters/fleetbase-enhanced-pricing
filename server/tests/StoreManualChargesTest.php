<?php

use Fleetbase\EnhancedPricing\Models\CrossCompanyReference;
use Fleetbase\EnhancedPricing\Models\ManualCharge;
use Fleetbase\EnhancedPricing\Tests\Support\Database;
use Fleetbase\EnhancedPricing\Tests\Support\Stores;
use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\Yaml\Yaml;

// Manual charges on orders: only the company's own orders, at most 20 on one, removed once.

beforeEach(function () {
    Database::fresh();
    Database::seed();
});

/**
 * @return array<string, mixed>
 */
function hourly(string $quantity = '1.5'): array
{
    return [
        'charge_key' => 'hourly',
        'product'    => 'HOURLY',
        'quantity'   => $quantity,
        'amount'     => 6300,
        'line_name'  => "Hourly charge ($quantity h)",
        'note'       => 'Waited at the dock',
    ];
}

test('a charge is stored with its version and who added it', function () {
    $version = Stores::profile()->currentVersion();

    $charge = Stores::for()->addManualCharge(Database::order('a'), $version, hourly('1.75'), 'user-1');

    expect($charge->refresh()->quantity)->toBe('1.750000')
        ->and($charge->amount)->toBe(6300)
        ->and($charge->profile_version_uuid)->toBe($version->uuid)
        ->and($charge->added_by_uuid)->toBe('user-1')
        ->and($charge->added_at)->not->toBeNull()
        ->and($charge->removed_at)->toBeNull();
});

test('a charge goes only on the company\'s own orders', function (\Closure $order) {
    $version = Stores::profile()->currentVersion();

    expect(Stores::refusal(fn () => Stores::for()->addManualCharge($order(), $version, hourly())))->toBe(['order-not-found'])
        ->and(ManualCharge::query()->count())->toBe(0);
})->with([
    'another company\'s order' => [fn () => Database::order('b')],
    'an unknown order'         => [fn () => 'no-such-order'],
    'a deleted order'          => [function () {
        Capsule::table('orders')->where('uuid', Database::order('a'))->update(['deleted_at' => '2026-01-01 00:00:00']);

        return Database::order('a');
    }],
]);

test('an order has at most 20 charges not removed', function () {
    $version = Stores::profile()->currentVersion();
    $store   = Stores::for();
    $charges = array_map(static fn () => $store->addManualCharge(Database::order('a'), $version, hourly()), range(1, 20));

    expect(Stores::refusal(fn () => $store->addManualCharge(Database::order('a'), $version, hourly())))
        ->toBe(['too-many-charges']);

    $store->removeManualCharge($charges[0]);
    expect($store->addManualCharge(Database::order('a'), $version, hourly())->uuid)->toBeString();
});

test('a charge is removed once, keeping who removed it', function () {
    $version = Stores::profile()->currentVersion();
    $charge  = Stores::for()->addManualCharge(Database::order('a'), $version, hourly());

    $removed = Stores::for()->removeManualCharge($charge, 'user-2');

    expect($removed->removed_by_uuid)->toBe('user-2')
        ->and($removed->removed_at)->not->toBeNull()
        ->and(Stores::refusal(fn () => Stores::for()->removeManualCharge($removed->refresh())))->toBe(['charge-removed']);
});

test('another company\'s version or charge is refused', function () {
    $versionB = Stores::profile('b')->currentVersion();
    $chargeB  = Stores::for('b')->addManualCharge(Database::order('b'), $versionB, hourly());

    expect(fn () => Stores::for('a')->addManualCharge(Database::order('a'), $versionB, hourly()))
        ->toThrow(CrossCompanyReference::class)
        ->and(fn () => Stores::for('a')->removeManualCharge($chargeB))
        ->toThrow(CrossCompanyReference::class)
        ->and($chargeB->refresh()->removed_at)->toBeNull();
});

test('every store problem key has English text', function () {
    $translations = Yaml::parseFile(__DIR__ . '/../../translations/en-us.yaml');
    $source       = '';
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/../src/Store')) as $file) {
        if ($file->isFile()) {
            $source .= file_get_contents($file->getPathname());
        }
    }
    preg_match_all("/(?:refuse|because|problem)\\('([a-z-]+)'/", $source, $keys);

    expect(count(array_unique($keys[1])))->toBeGreaterThan(15);
    foreach (array_unique($keys[1]) as $key) {
        expect($translations['enhanced-pricing']['store'][$key] ?? null)->toBeString("store.$key has no English text");
    }
});
