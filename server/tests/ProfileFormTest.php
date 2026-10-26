<?php

use Fleetbase\EnhancedPricing\Admin\ProfileForm;
use Fleetbase\EnhancedPricing\Profile\ProfileShapes;
use Fleetbase\EnhancedPricing\Profile\ProfileValidator;
use Fleetbase\EnhancedPricing\Tests\Support\Profiles;

// The console's profile editor draws its forms from ProfileForm::spec(). These tests hold the spec
// to the profile checks, so the form can't offer a field the checks refuse, miss one they need,
// or call a required field optional.

/**
 * A full item of each kind and variant, every field set, as the form would save it. Each is
 * added to the example profile, billed as a new product NEWP.
 *
 * @return array<string, array{section: string, kind: string, variant: string|null, item: array<string, mixed>}>
 */
function formSamples(): array
{
    $rule   = ['name' => 'New', 'product' => 'NEWP'];
    $limits = [
        'minimum'         => null,
        'maximum'         => null,
        'effective_from'  => null,
        'effective_until' => null,
        'enabled'         => true,
    ];
    $sample = static fn (string $section, string $kind, ?string $variant, array $item): array => [
        'section' => $section,
        'kind'    => $kind,
        'variant' => $variant,
        'item'    => $item,
    ];
    $attribute = ['key' => 'new_attr', 'name' => 'New', 'scope' => 'order', 'required' => false, 'required_for' => []];
    $rate      = ['key' => 'new_rate'] + $rule
        + ['line_name' => ['text' => 'New'], 'minimum' => null, 'enabled' => true];
    $fee       = ['key' => 'new_fee'] + $rule + ['applies_to' => ['TIERED']] + $limits;
    $subtotal  = ['key' => 'new_sub'] + $rule + ['applies_to' => ['TIERED'], 'requires' => [], 'skipped_for' => []]
        + $limits;
    $charge    = ['key' => 'new_charge'] + $rule
        + ['offered' => true, 'effective_from' => null, 'effective_until' => null];

    return [
        'product'               => $sample('products', 'product', null, ['code' => 'NEWQ', 'name' => 'New']),
        'boolean attribute'     => $sample('attributes', 'attribute', 'boolean', $attribute + ['type' => 'boolean']),
        'choice attribute'      => $sample('attributes', 'attribute', 'choice', $attribute + [
            'type'    => 'choice',
            'choices' => [['key' => 'a', 'name' => 'A']],
        ]),
        'number attribute'      => $sample('attributes', 'attribute', 'number', $attribute + [
            'type'    => 'number',
            'unit'    => 'h',
            'maximum' => '100',
        ]),
        'entity type'           => $sample('entity_types', 'entity_type', null, [
            'key'       => 'new_type',
            'name'      => 'New',
            'own_price' => ['column' => 'separate_price', 'product' => 'NEWP'],
        ]),
        'rate table'            => $sample('rate_tables', 'rate_table', null, [
            'key'     => 'new_table',
            'columns' => [['key' => 'code', 'type' => 'text', 'required' => true]],
            'row_key' => ['code'],
        ]),
        'tiers service rate'    => $sample('service_rates', 'service_rate', 'tiers', $rate + [
            'rate_table' => 'rows',
            'method'     => 'tiers',
            'tiers'      => [
                ['up_to' => ['value' => 2], 'price' => ['column' => 'first']],
                ['up_to' => null, 'price' => ['value' => 100]],
            ],
        ]),
        'per meter service rate' => $sample('service_rates', 'service_rate', 'per_meter', $rate + [
            'rate_table' => null,
            'method'     => 'per_meter',
            'per_meter'  => [
                'rate'             => 100,
                'base_fee'         => 0,
                'minimum_distance' => '0',
                'maximum_distance' => '100',
                'rounding'         => 'up',
            ],
        ]),
        'fixed service rate'    => $sample('service_rates', 'service_rate', 'fixed_rate', $rate + [
            'rate_table' => null,
            'method'     => 'fixed_rate',
            'fixed_rate' => ['amount' => ['value' => 100]],
        ]),
        'peak hours fee'        => $sample('fees', 'fee', 'peak_hours', $fee + [
            'kind'       => 'peak_hours',
            'charge'     => 'per_order',
            'amount'     => 100,
            'if_missing' => 'stop',
            'window'     => ['from' => '21:00', 'to' => '06:00'],
            'weekdays'   => [6, 7],
            'holidays'   => null,
        ]),
        'parcel fee'            => $sample('fees', 'fee', 'parcel', $fee + [
            'kind'       => 'parcel',
            'charge'     => 'per_unit',
            'basis'      => 'weight',
            'measure'    => 'dimensional',
            'divisor'    => '5000',
            'unit'       => 'kg',
            'scope'      => 'entity',
            'thresholds' => [['over' => '30', 'amount' => 40]],
        ]),
        'additional fee'        => $sample('fees', 'fee', 'additional', $fee + [
            'kind'       => 'additional',
            'charge'     => 'per_order',
            'amount'     => 100,
            'if_missing' => 'stop',
            'attribute'  => 'extra',
            'equals'     => null,
        ]),
        'percentage subtotal fee' => $sample('subtotal_fees', 'subtotal_fee', 'percentage', $subtotal + [
            'mode'       => 'percentage',
            'percentage' => 1000,
            'charge'     => 'per_order',
            'if_missing' => 'stop',
        ]),
        'flat subtotal fee'     => $sample('subtotal_fees', 'subtotal_fee', 'flat', $subtotal + [
            'mode'       => 'flat',
            'amount'     => 100,
            'charge'     => 'per_line',
            'if_missing' => 'stop',
        ]),
        'amount manual charge'  => $sample('manual_charges', 'manual_charge', 'amount', $charge + [
            'amount'   => 100,
            'quantity' => ['unit' => 'h', 'step' => '0.25', 'maximum' => '24'],
        ]),
        'entered manual charge' => $sample('manual_charges', 'manual_charge', 'entered', $charge + [
            'entered' => ['minimum' => 100, 'maximum' => 1000],
        ]),
    ];
}

/**
 * The fields the spec gives an item of a kind and variant, with the discriminator, if any.
 *
 * @return array<string, array<string, mixed>>
 */
function formFields(string $kind, ?string $variant): array
{
    $spec   = ProfileForm::spec()['kinds'][$kind];
    $fields = array_merge($spec['fields'], $variant === null ? [] : $spec['variants'][$variant]);
    $named  = [];
    foreach ($fields as $field) {
        $named[$field['name']] = $field;
    }
    if (($spec['discriminator'] ?? null) !== null) {
        $named[$spec['discriminator']] = ['name' => $spec['discriminator'], 'type' => 'enum'];
    }

    return $named;
}

/**
 * @param array<string, mixed> $item
 *
 * @return list<string> the problem paths, if the example profile with this item added is refused
 */
function formProblems(string $section, array $item): array
{
    $profile               = Profiles::example();
    $profile['products'][] = ['code' => 'NEWP', 'name' => 'New product'];
    $profile[$section][]   = $item;

    return array_map(
        static fn ($problem): string => $problem->path . ' ' . $problem->key,
        (new ProfileValidator())->check($profile),
    );
}

/**
 * An item with one field left out, and with the fields used only when that one is set (`when`).
 *
 * @param array<string, array<string, mixed>> $fields
 * @param array<string, mixed>                $item
 *
 * @return array<string, mixed>
 */
function formWithout(array $fields, array $item, string $name): array
{
    unset($item[$name]);
    foreach ($fields as $other => $field) {
        if (array_key_exists($name, $field['when'] ?? [])) {
            unset($item[$other]);
        }
    }

    return $item;
}

test('every section of a profile is in the form', function () {
    $sections = array_column(ProfileForm::spec()['sections'], 'key');
    $general  = ['currency', 'scheduling_timezone', 'distance_unit', 'default_entity_type'];
    $profile  = array_diff(array_keys(ProfileShapes::topLevel()), $general);

    expect($sections)->toBe(array_merge(['general'], array_values($profile)))
        ->and(array_column(formFields('profile', null), 'name'))
        ->toBe($general);
});

test('a full item of each kind passes the profile checks', function (
    string $section,
    string $kind,
    ?string $variant,
    array $item,
) {
    expect(array_keys($item))->toEqualCanonicalizing(array_keys(formFields($kind, $variant)))
        ->and(formProblems($section, $item))->toBe([]);
})->with(formSamples());

test('each optional field can be left out, and each other field is needed', function (
    string $section,
    string $kind,
    ?string $variant,
    array $item,
) {
    $fields = formFields($kind, $variant);
    foreach ($fields as $name => $field) {
        $without = formWithout($fields, $item, $name);
        if ($kind === 'service_rate' && $name === 'rate_table') {
            // The example profile has an entity type with its own price, so every tiered rate
            // needs a table there; optional in general, needed by this profile.
            continue;
        }
        $problems = formProblems($section, $without);
        if (($field['optional'] ?? false) === true) {
            expect($problems)->toBe([], "$kind.$name is optional");
        } else {
            expect($problems)->not->toBe([], "$kind.$name is needed");
        }
    }
})->with(formSamples());

test('an optional field\'s default is what the checks fill in', function (
    string $section,
    string $kind,
    ?string $variant,
    array $item,
) {
    $fields = formFields($kind, $variant);
    foreach ($fields as $name => $field) {
        if (($field['optional'] ?? false) === true && ($kind !== 'service_rate' || $name !== 'rate_table')) {
            $profile               = Profiles::example();
            $profile['products'][] = ['code' => 'NEWP', 'name' => 'New product'];
            $profile[$section][]   = formWithout($fields, $item, $name);
            $normalized            = (new ProfileValidator())->normalize($profile);
            $saved                 = $normalized[$section][count($normalized[$section]) - 1];
            expect($saved[$name])->toBe($field['default'], "$kind.$name");
        }
    }
    expect($fields)->not->toBe([]);
})->with(formSamples());

test('a field the form does not have is refused', function (
    string $section,
    string $kind,
    ?string $variant,
    array $item,
) {
    $index = count(Profiles::example()[$section]) + ($section === 'products' ? 1 : 0);

    expect(formProblems($section, $item + ['not_a_field' => 1]))
        ->toContain("$section.$index.not_a_field enhanced-pricing.profile.unknown-field");
})->with(formSamples());

test('the fee, subtotal fee, manual charge, rate table and per meter forms list the checks\' fields', function () {
    foreach (['peak_hours', 'parcel', 'additional'] as $kind) {
        expect(array_keys(formFields('fee', $kind)))
            ->toEqualCanonicalizing(array_keys(ProfileShapes::feeFields($kind)));
    }
    $subtotal = array_keys(formFields('subtotal_fee', 'percentage') + formFields('subtotal_fee', 'flat'));
    expect($subtotal)->toEqualCanonicalizing(array_keys(ProfileShapes::subtotalFeeFields()));
    $manual = array_keys(formFields('manual_charge', 'amount') + formFields('manual_charge', 'entered'));
    expect($manual)->toEqualCanonicalizing(array_keys(ProfileShapes::manualCharge()))
        ->and(array_keys(formFields('rate_table', null)))->toEqualCanonicalizing(array_keys(ProfileShapes::rateTable()))
        ->and(array_keys(formFields('per_meter', null)))->toEqualCanonicalizing(array_keys(ProfileShapes::perMeter()));
});

test('every enum offers exactly what the checks accept', function () {
    // A wrong option is refused by the full-item test only for the option it uses, so each one is
    // tried here.
    foreach (formSamples() as $name => $sample) {
        foreach (formFields($sample['kind'], $sample['variant']) as $field => $spec) {
            foreach ($spec['options'] ?? [] as $option) {
                $item   = $sample['item'];
                $linked = in_array($field, ['charge', 'measure', 'basis', 'unit', 'scope'], true);
                if ($linked && $sample['kind'] === 'fee') {
                    continue; // these depend on each other, and are tried in the fee tests
                }
                $item[$field] = $option;
                expect(formProblems($sample['section'], $item))->toBe([], "$name $field $option");
            }
        }
    }
});

test('every ref and kind the form names exists', function () {
    $spec  = ProfileForm::spec();
    $refs  = [
        'products', 'entity_types', 'rate_tables', 'columns', 'text_columns', 'money_columns',
        'integer_columns', 'attributes', 'choices', 'holiday_lists', 'service_rates',
    ];
    $check = function (array $field) use ($spec, $refs, &$check): void {
        if (isset($field['ref'])) {
            expect($refs)->toContain($field['ref']);
        }
        if (in_array($field['type'], ['object', 'list'], true)) {
            expect($spec['kinds'])->toHaveKey($field['of']);
        }
        foreach ($field['choices'] ?? [] as $choice) {
            $check(['name' => 'choice'] + $choice);
        }
    };
    foreach ($spec['kinds'] as $kind) {
        foreach (array_merge($kind['fields'], ...array_values($kind['variants'] ?? [])) as $field) {
            $check($field);
        }
    }
    foreach ($spec['sections'] as $section) {
        expect($spec['kinds'])->toHaveKey($section['kind']);
    }
});
