<?php

use Fleetbase\EnhancedPricing\Profile\InvalidProfile;
use Fleetbase\EnhancedPricing\Profile\ProfileValidator;
use Fleetbase\EnhancedPricing\Tests\Support\Profiles;

// The profile checks. Each case changes one thing in the example profile and expects a problem
// at that path with that key.

test('the example profile has no problems', function () {
    expect((new ProfileValidator())->check(Profiles::example()))->toBe([]);
});

test('normalize fills in optional fields with their defaults', function () {
    $profile = Profiles::example();
    unset(
        $profile['holiday_lists'],
        $profile['fees'][2]['minimum'],
        $profile['fees'][2]['enabled'],
        $profile['fees'][2]['if_missing'],
    );
    unset($profile['service_rates'][1]['per_meter']['base_fee'], $profile['manual_charges'][1]['amount']);

    $normalized = (new ProfileValidator())->normalize($profile);

    expect($normalized['holiday_lists'])->toBe([])
        ->and($normalized['fees'][2]['minimum'])->toBeNull()
        ->and($normalized['fees'][2]['enabled'])->toBeTrue()
        ->and($normalized['fees'][2]['if_missing'])->toBe('stop')
        ->and($normalized['service_rates'][1]['per_meter']['base_fee'])->toBe(0)
        ->and($normalized['manual_charges'][1]['amount'])->toBeNull()
        ->and($normalized['manual_charges'][0]['quantity']['step'])->toBe('0.25')
        ->and($normalized['entity_types'][0])->toBe(['key' => 'standard', 'name' => 'Standard', 'own_price' => null])
        ->and($normalized['entity_types'][1]['own_price'])
        ->toBe(['column' => 'separate_price', 'product' => 'SEPARATE']);
});

test('normalize refuses an invalid profile with every problem', function () {
    $profile = Profiles::set(Profiles::example(), 'currency', 'usd');
    $profile = Profiles::set($profile, 'distance_unit', 'yd');

    try {
        (new ProfileValidator())->normalize($profile);
        $this->fail('The profile should have been refused.');
    } catch (InvalidProfile $invalid) {
        expect(array_map(static fn ($problem) => $problem->path, $invalid->problems))
            ->toBe(['currency', 'distance_unit']);
    }
});

test('an entity type with own_price null counts in the tiers', function () {
    $profile = Profiles::set(Profiles::example(), 'entity_types.1.own_price', null);

    expect((new ProfileValidator())->check($profile))->toBe([])
        ->and((new ProfileValidator())->normalize($profile)['entity_types'][1]['own_price'])->toBeNull();
});

test('a tiered service rate needs no rate table when no entity type has its own price', function () {
    $profile = Profiles::set(Profiles::example(), 'entity_types', [['key' => 'standard', 'name' => 'Standard']]);
    $profile = Profiles::set($profile, 'service_rates.0.rate_table', null);
    $profile = Profiles::set($profile, 'service_rates.0.line_name', ['text' => 'Tiered delivery']);
    $profile = Profiles::set($profile, 'service_rates.0.tiers', [['up_to' => null, 'price' => ['value' => 2700]]]);

    expect((new ProfileValidator())->check($profile))->toBe([]);
});

test('a problem in one part of the profile', function (string $path, mixed $value, string $problemPath, string $key) {
    if ($value === '__remove__') {
        $profile = Profiles::remove(Profiles::example(), $path);
    } else {
        $profile = Profiles::set(Profiles::example(), $path, $value);
    }
    $problems = (new ProfileValidator())->check($profile);

    expect(array_map(static fn ($problem) => [$problem->path, $problem->key], $problems))
        ->toContain([$problemPath, 'enhanced-pricing.profile.' . $key]);
})->with([
    // Top level and conventions.
    'unknown top-level field'      => ['colour', 'red', 'colour', 'unknown-field'],
    'missing currency'             => ['currency', '__remove__', 'currency', 'missing-field'],
    'lowercase currency'           => ['currency', 'usd', 'currency', 'wrong-type'],
    'unknown timezone'             => ['timezone', 'Mars/Olympus', 'timezone', 'wrong-type'],
    'distance unit'                => ['distance_unit', 'yd', 'distance_unit', 'not-one-of'],
    'no products'                  => ['products', [], 'products', 'too-few'],
    'products not a list'          => ['products', ['a' => 1], 'products', 'wrong-type'],
    'too many products'            => [
        'products',
        array_map(static fn ($n) => ['code' => "P$n", 'name' => 'P'], range(1, 51)),
        'products',
        'too-many',
    ],
    'duplicate product'            => ['products.1.code', 'TIERED', 'products.1.code', 'duplicate'],
    'bad product code'             => ['products.0.code', 'has space', 'products.0.code', 'wrong-type'],
    'long name'                    => ['products.0.name', str_repeat('a', 256), 'products.0.name', 'too-long'],
    'empty name'                   => ['products.0.name', '', 'products.0.name', 'too-short'],
    'unknown product field'        => ['products.0.price', 1, 'products.0.price', 'unknown-field'],
    // Attributes.
    'bad attribute key'            => ['attributes.0.key', 'Extra', 'attributes.0.key', 'wrong-type'],
    'attribute type'               => ['attributes.0.type', 'text', 'attributes.0.type', 'not-one-of'],
    'choices on a boolean'         => [
        'attributes.0.choices',
        [['key' => 'a', 'name' => 'A']],
        'attributes.0.choices',
        'unknown-field',
    ],
    'required for unknown rate'    => [
        'attributes.0.required',
        ['nope'],
        'attributes.0.required.0',
        'unknown-reference',
    ],
    'choice with no choices'       => [
        'attributes.0',
        ['key' => 'size', 'name' => 'Size', 'scope' => 'order', 'type' => 'choice'],
        'attributes.0.choices',
        'missing-field',
    ],
    // Entity types.
    'default type unknown'         => ['default_entity_type', 'nope', 'default_entity_type', 'unknown-reference'],
    'own price without column'     => [
        'entity_types.1.own_price.column',
        '__remove__',
        'entity_types.1.own_price.column',
        'missing-field',
    ],
    'own price without product'    => [
        'entity_types.1.own_price.product',
        '__remove__',
        'entity_types.1.own_price.product',
        'missing-field',
    ],
    'own price not an object'      => [
        'entity_types.1.own_price',
        'separate_price',
        'entity_types.1.own_price',
        'wrong-type',
    ],
    'own price empty'              => [
        'entity_types.1.own_price',
        [],
        'entity_types.1.own_price.column',
        'missing-field',
    ],
    'own price a list'             => [
        'entity_types.1.own_price',
        ['separate_price', 'SEPARATE'],
        'entity_types.1.own_price',
        'wrong-type',
    ],
    'own price column unknown'     => [
        'entity_types.1.own_price.column',
        'nope',
        'entity_types.1.own_price.column',
        'unknown-reference',
    ],
    'own price column not money'   => [
        'entity_types.1.own_price.column',
        'code',
        'entity_types.1.own_price.column',
        'column-wrong-type',
    ],
    'own price product unknown'    => [
        'entity_types.1.own_price.product',
        'NOPE',
        'entity_types.1.own_price.product',
        'unknown-product',
    ],
    'unknown own price field'      => [
        'entity_types.1.own_price.amount',
        900,
        'entity_types.1.own_price.amount',
        'unknown-field',
    ],
    // The old `priced` field is refused, so a profile written for it fails loudly instead of losing its own prices.
    'old priced field'             => ['entity_types.0.priced', 'tiers', 'entity_types.0.priced', 'unknown-field'],
    'column beside own price'      => [
        'entity_types.1.column',
        'separate_price',
        'entity_types.1.column',
        'unknown-field',
    ],
    // Rate tables.
    'row key not a column'         => [
        'rate_tables.0.row_key',
        ['nope'],
        'rate_tables.0.row_key.0',
        'unknown-reference',
    ],
    'row key not required'         => [
        'rate_tables.0.row_key',
        ['separate_price'],
        'rate_tables.0.row_key.0',
        'row-key-not-required',
    ],
    'column type'                  => [
        'rate_tables.0.columns.0.type',
        'date',
        'rate_tables.0.columns.0.type',
        'not-one-of',
    ],
    'duplicate column'             => [
        'rate_tables.0.columns.1.key',
        'code',
        'rate_tables.0.columns.1.key',
        'duplicate',
    ],
    // Service rates.
    'unknown method'               => ['service_rates.0.method', 'per_stop', 'service_rates.0.method', 'not-one-of'],
    'tiers without a table'        => [
        'service_rates.0.rate_table',
        null,
        'service_rates.0.rate_table',
        'tiers-need-rate-table',
    ],
    'unknown rate table'           => [
        'service_rates.0.rate_table',
        'nope',
        'service_rates.0.rate_table',
        'unknown-reference',
    ],
    'unknown service product'      => ['service_rates.0.product', 'NOPE', 'service_rates.0.product', 'unknown-product'],
    'field of another method'      => [
        'service_rates.0.per_meter',
        ['rate' => 1, 'maximum_distance' => 10, 'rounding' => 'up'],
        'service_rates.0.per_meter',
        'unknown-field',
    ],
    'no tiers'                     => ['service_rates.0.tiers', [], 'service_rates.0.tiers', 'too-few'],
    'open tier not last'           => [
        'service_rates.0.tiers.0.up_to',
        null,
        'service_rates.0.tiers.0.up_to',
        'open-tier-not-last',
    ],
    'tiers not increasing'         => [
        'service_rates.0.tiers.1.up_to',
        ['value' => 2],
        'service_rates.0.tiers.1.up_to',
        'not-increasing',
    ],
    'tier up to zero'              => [
        'service_rates.0.tiers.0.up_to',
        ['value' => 0],
        'service_rates.0.tiers.0.up_to.value',
        'out-of-range',
    ],
    'tier column unknown'          => [
        'service_rates.0.tiers.0.price',
        ['column' => 'nope'],
        'service_rates.0.tiers.0.price.column',
        'unknown-reference',
    ],
    'tier price column not money'  => [
        'service_rates.0.tiers.0.price',
        ['column' => 'code'],
        'service_rates.0.tiers.0.price.column',
        'column-wrong-type',
    ],
    'tier price both kinds'        => [
        'service_rates.0.tiers.0.price',
        ['column' => 'first', 'value' => 1],
        'service_rates.0.tiers.0.price',
        'needs-exactly-one',
    ],
    'line name not text column'    => [
        'service_rates.0.line_name',
        ['column' => 'first'],
        'service_rates.0.line_name.column',
        'column-wrong-type',
    ],
    'column without a table'       => [
        'service_rates.1.line_name',
        ['column' => 'code'],
        'service_rates.1.line_name.column',
        'column-needs-rate-table',
    ],
    'per meter no maximum'         => [
        'service_rates.1.per_meter.maximum_distance',
        '__remove__',
        'service_rates.1.per_meter.maximum_distance',
        'missing-field',
    ],
    'per meter maximum too far'    => [
        'service_rates.1.per_meter.maximum_distance',
        20001,
        'service_rates.1.per_meter.maximum_distance',
        'out-of-range',
    ],
    'minimum above maximum dist'   => [
        'service_rates.1.per_meter.minimum_distance',
        2000,
        'service_rates.1.per_meter.minimum_distance',
        'minimum-above-maximum',
    ],
    'rounding'                     => [
        'service_rates.1.per_meter.rounding',
        'down',
        'service_rates.1.per_meter.rounding',
        'not-one-of',
    ],
    'number with exponent'         => [
        'service_rates.1.per_meter.minimum_distance',
        '1e1',
        'service_rates.1.per_meter.minimum_distance',
        'wrong-type',
    ],
    'number with 7 decimals'       => [
        'service_rates.1.per_meter.minimum_distance',
        '0.0000001',
        'service_rates.1.per_meter.minimum_distance',
        'wrong-type',
    ],
    'duplicate service rate'       => ['service_rates.1.key', 'tiered', 'service_rates.1.key', 'duplicate'],
    'money as a float'             => [
        'service_rates.1.per_meter.rate',
        2.1,
        'service_rates.1.per_meter.rate',
        'wrong-type',
    ],
    'money too large'              => [
        'service_rates.1.per_meter.rate',
        100000001,
        'service_rates.1.per_meter.rate',
        'out-of-range',
    ],
    // Fees.
    'unknown fee kind'             => ['fees.0.kind', 'discount', 'fees.0.kind', 'not-one-of'],
    'charge for the kind'          => ['fees.2.charge', 'per_unit', 'fees.2.charge', 'not-one-of'],
    'no applies to'                => ['fees.0.applies_to', [], 'fees.0.applies_to', 'too-few'],
    'unknown applies to'           => ['fees.0.applies_to', ['NOPE'], 'fees.0.applies_to.0', 'unknown-product'],
    'own product'                  => ['fees.2.applies_to', ['PEAK'], 'fees.2.applies_to.0', 'own-product'],
    'later product'                => ['fees.0.applies_to', ['PARCEL'], 'fees.0.applies_to.0', 'later-product'],
    'subtotal product in a fee'    => ['fees.0.applies_to', ['PERCENT'], 'fees.0.applies_to.0', 'later-product'],
    'if missing value'             => ['fees.0.if_missing', 'ignore', 'fees.0.if_missing', 'not-one-of'],
    'bad date'                     => ['fees.0.effective_from', '2027-13-01', 'fees.0.effective_from', 'wrong-type'],
    'unknown attribute'            => ['fees.0.attribute', 'nope', 'fees.0.attribute', 'unknown-reference'],
    'equals on a boolean'          => ['fees.0.equals', 'yes', 'fees.0.equals', 'equals-not-allowed'],
    'per unit on a boolean'        => ['fees.0.charge', 'per_unit', 'fees.0.charge', 'per-unit-needs-number'],
    'amount on a parcel fee'       => ['fees.1.amount', 100, 'fees.1.amount', 'unknown-field'],
    'parcel entity per order'      => ['fees.1.charge', 'per_order', 'fees.1.charge', 'not-one-of'],
    'thresholds not increasing'    => [
        'fees.1.thresholds',
        [['over' => 30, 'amount' => 1], ['over' => 30, 'amount' => 2]],
        'fees.1.thresholds.1.over',
        'not-increasing',
    ],
    'too many thresholds'          => [
        'fees.1.thresholds',
        array_map(static fn ($n) => ['over' => $n, 'amount' => 1], range(1, 11)),
        'fees.1.thresholds',
        'too-many',
    ],
    'divisor on actual'            => ['fees.1.divisor', 5000, 'fees.1.divisor', 'divisor-not-allowed'],
    'dimensional without divisor'  => ['fees.1.measure', 'dimensional', 'fees.1.divisor', 'missing-field'],
    'weight unit for volume'       => ['fees.1.basis', 'volume', 'fees.1.unit', 'not-one-of'],
    'peak with nothing set'        => ['fees.2.weekdays', null, 'fees.2', 'needs-one-of'],
    'weekday out of range'         => ['fees.2.weekdays', [8], 'fees.2.weekdays.0', 'out-of-range'],
    'no weekdays'                  => ['fees.2.weekdays', [], 'fees.2.weekdays', 'too-few'],
    'window from equals to'        => [
        'fees.2.window',
        ['from' => '09:00', 'to' => '09:00'],
        'fees.2.window.to',
        'window-empty',
    ],
    'bad time'                     => [
        'fees.2.window',
        ['from' => '24:00', 'to' => '09:00'],
        'fees.2.window.from',
        'wrong-type',
    ],
    'unknown holiday list'         => ['fees.2.holidays', 'nope', 'fees.2.holidays', 'unknown-reference'],
    'duplicate fee key'            => ['fees.1.key', 'additional', 'fees.1.key', 'duplicate'],
    // Subtotal fees.
    'percentage per line'          => ['subtotal_fees.0.charge', 'per_line', 'subtotal_fees.0.charge', 'not-one-of'],
    'amount on percentage'         => ['subtotal_fees.0.amount', 100, 'subtotal_fees.0.amount', 'not-allowed-for-mode'],
    'percentage on flat'           => [
        'subtotal_fees.1.percentage',
        100,
        'subtotal_fees.1.percentage',
        'not-allowed-for-mode',
    ],
    'percentage too large'         => [
        'subtotal_fees.0.percentage',
        100001,
        'subtotal_fees.0.percentage',
        'out-of-range',
    ],
    'product in two lists'         => [
        'subtotal_fees.2.skipped_for',
        ['ADDITIONAL'],
        'subtotal_fees.2.skipped_for.0',
        'product-in-two-lists',
    ],
    'requires a later product'     => [
        'subtotal_fees.1.requires',
        ['FLAT_B'],
        'subtotal_fees.1.requires.0',
        'later-product',
    ],
    'skips its own product'        => [
        'subtotal_fees.1.skipped_for',
        ['FLAT_A'],
        'subtotal_fees.1.skipped_for.0',
        'own-product',
    ],
    // Manual charges.
    'both amount and entered'      => [
        'manual_charges.0.entered',
        ['minimum' => 1, 'maximum' => 2],
        'manual_charges.0',
        'needs-exactly-one',
    ],
    'neither amount nor entered'   => ['manual_charges.0.amount', null, 'manual_charges.0', 'needs-exactly-one'],
    'entered with a quantity'      => [
        'manual_charges.1.quantity',
        ['unit' => 'h', 'step' => '1', 'maximum' => '2'],
        'manual_charges.1.quantity',
        'entered-takes-no-quantity',
    ],
    'entered minimum above max'    => [
        'manual_charges.1.entered',
        ['minimum' => 500, 'maximum' => 100],
        'manual_charges.1.entered.minimum',
        'minimum-above-maximum',
    ],
    'entered without maximum'      => [
        'manual_charges.1.entered',
        ['minimum' => 500],
        'manual_charges.1.entered.maximum',
        'missing-field',
    ],
    'step of zero'                 => [
        'manual_charges.0.quantity.step',
        '0',
        'manual_charges.0.quantity.step',
        'out-of-range',
    ],
    'maximum not a step multiple'  => [
        'manual_charges.0.quantity.maximum',
        '1.1',
        'manual_charges.0.quantity.maximum',
        'not-a-step',
    ],
    // Holiday lists.
    'holiday list bad date'        => [
        'holiday_lists',
        ['main' => ['2027-02-30']],
        'holiday_lists.main.0',
        'wrong-type',
    ],
    'holiday list bad name'        => ['holiday_lists', ['Main' => []], 'holiday_lists.Main', 'wrong-type'],
]);

test('values that must agree with each other', function (array $values, string $problemPath, string $key) {
    $profile = Profiles::example();
    foreach ($values as $path => $value) {
        $profile = Profiles::set($profile, $path, $value);
    }

    $problems = (new ProfileValidator())->check($profile);

    expect(array_map(static fn ($problem) => [$problem->path, $problem->key], $problems))
        ->toContain([$problemPath, 'enhanced-pricing.profile.' . $key]);
})->with([
    'minimum above maximum'     => [
        ['fees.0.minimum' => 2000, 'fees.0.maximum' => 1000],
        'fees.0.minimum',
        'minimum-above-maximum',
    ],
    'until before from'         => [
        ['fees.0.effective_from' => '2027-05-01', 'fees.0.effective_until' => '2027-04-30'],
        'fees.0.effective_until',
        'until-before-from',
    ],
    'parcel order per entity'   => [
        ['fees.1.scope' => 'order', 'fees.1.charge' => 'per_entity'],
        'fees.1.charge',
        'not-one-of',
    ],
    'choice fee without equals' => [
        ['attributes.0' => [
            'key'     => 'extra',
            'name'    => 'Size',
            'scope'   => 'order',
            'type'    => 'choice',
            'choices' => [['key' => 's', 'name' => 'Small']],
        ]],
        'fees.0.equals',
        'missing-field',
    ],
    'choice fee unknown equals' => [
        [
            'attributes.0' => [
                'key'     => 'extra',
                'name'    => 'Size',
                'scope'   => 'order',
                'type'    => 'choice',
                'choices' => [['key' => 's', 'name' => 'Small']],
            ],
            'fees.0.equals' => 'l',
        ],
        'fees.0.equals',
        'unknown-reference',
    ],
    'duplicate applies to'    => [['fees.0.applies_to' => ['TIERED', 'TIERED']], 'fees.0.applies_to.1', 'duplicate'],
    'volume with a measure'   => [
        ['fees.1.basis' => 'volume', 'fees.1.unit' => 'm3'],
        'fees.1.measure',
        'measure-not-allowed',
    ],
]);

test('a profile above 64 KB is refused', function () {
    $profile = Profiles::set(
        Profiles::example(),
        'holiday_lists',
        array_fill_keys(array_map(static fn ($n) => "l$n", range(1, 10)), array_fill(0, 400, '2027-01-01')),
    );
    $profile = Profiles::set(
        $profile,
        'products',
        array_map(static fn ($n) => ['code' => "P$n", 'name' => str_repeat('n', 255)], range(1, 50)),
    );

    $keys = array_map(static fn ($problem) => $problem->key, (new ProfileValidator())->check($profile));

    expect($keys)->toContain('enhanced-pricing.profile.too-large');
});

test('every problem and pricing key has English text', function () {
    $translations = Symfony\Component\Yaml\Yaml::parseFile(__DIR__ . '/../../translations/en-us.yaml');
    $source       = '';
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/../src')) as $file) {
        if ($file->isFile()) {
            $source .= file_get_contents($file->getPathname());
        }
    }
    preg_match_all("/Problem::atPath\\((?:[^()]|\\([^()]*\\))*?,\\s*'([a-z-]+)'/", $source, $problems);
    preg_match_all("/(?:fail|because|note)\\('([a-z-]+)'/", $source, $pricing);

    expect(count(array_unique($problems[1])))->toBeGreaterThan(30)
        ->and(count(array_unique($pricing[1])))->toBeGreaterThan(25);
    foreach (array_unique($problems[1]) as $key) {
        expect($translations['enhanced-pricing']['profile'][$key] ?? null)
            ->toBeString("profile.$key has no English text");
    }
    foreach (array_unique($pricing[1]) as $key) {
        expect($translations['enhanced-pricing']['pricing'][$key] ?? null)
            ->toBeString("pricing.$key has no English text");
    }
});
