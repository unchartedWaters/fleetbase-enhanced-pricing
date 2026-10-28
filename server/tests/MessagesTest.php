<?php

use Fleetbase\EnhancedPricing\Admin\ProfileForm;
use Fleetbase\EnhancedPricing\Pricing\ManualChargeInput;
use Fleetbase\EnhancedPricing\Profile\InvalidProfile;
use Fleetbase\EnhancedPricing\Profile\Problem;
use Fleetbase\EnhancedPricing\Profile\ProfileValidator;
use Fleetbase\EnhancedPricing\Tests\Support\Messages;
use Fleetbase\EnhancedPricing\Tests\Support\Orders;
use Fleetbase\EnhancedPricing\Tests\Support\Profiles;

// The messages people see: every key has English text, every placeholder in that text gets a
// value, and each message names the right cause.

test('every problem and pricing key has English text, however its call is written', function () {
    $all      = Messages::translations();
    $problems = Messages::keysPassedTo(['atPath']);
    $pricing  = Messages::keysPassedTo(['fail', 'because', 'note']);

    expect($problems)->toContain('unknown-field')
        ->and(count($problems))->toBeGreaterThan(30)
        ->and(count($pricing))->toBeGreaterThan(25);
    foreach ($problems as $key) {
        expect($all['profile'][$key] ?? null)->toBeString("profile.$key has no English text");
    }
    foreach ($pricing as $key) {
        expect($all['pricing'][$key] ?? null)->toBeString("pricing.$key has no English text");
    }
});

test('every admin page refusal has English text', function () {
    $all   = Messages::translations();
    $admin = Messages::keysPassedTo(['invalid']);

    expect($admin)->toContain('change-not-allowed')
        ->and(count($admin))->toBeGreaterThanOrEqual(6);
    foreach ($admin as $key) {
        expect($all['admin'][$key] ?? null)->toBeString("admin.$key has no English text");
    }
});

test('a misspelt field is named in the message', function () {
    $profile = Profiles::set(Profiles::example(), 'entity_types.1.priced', true);

    $problems = (new ProfileValidator())->check($profile);
    $unknown  = array_values(array_filter(
        $problems,
        static fn (Problem $problem) => str_ends_with($problem->key, 'unknown-field'),
    ));

    expect($unknown)->toHaveCount(1)
        ->and(Messages::english($unknown[0]->key, $unknown[0]->params))->toBe("priced isn't a field here.");
});

test('profile problems fill every placeholder, with types and kinds in plain words', function (
    string $path,
    mixed $value,
    string $text,
) {
    $profile = Profiles::set(Profiles::example(), $path, $value);

    try {
        (new ProfileValidator())->normalize($profile);
    } catch (InvalidProfile $invalid) {
        $texts = array_map(
            static fn (Problem $problem) => Messages::english($problem->key, $problem->params),
            $invalid->problems,
        );

        expect($texts)->toContain($text);
        foreach ($texts as $shown) {
            expect($shown)->not->toMatch('/\{[a-z_]+\}|<no text/');
        }

        return;
    }
    throw new RuntimeException('The profile was accepted.');
})->with([
    'a number with no maximum'   => [
        'service_rates.1.per_meter.minimum_distance',
        -1,
        'The value must be at least 0.',
    ],
    'money as text'                => [
        'service_rates.1.per_meter.rate',
        'x',
        'The value must be a whole number of cents.',
    ],
    'a divisor of zero'            => ['fees.1.divisor', 0, 'The value must be more than 0.'],
    'a number above its maximum'   => [
        'service_rates.1.per_meter.maximum_distance',
        20001,
        'The value must be at most 20000.',
    ],
    'money as a decimal'          => ['fees.0.amount', 27.5, 'The value must be a whole number of cents.'],
    'a value left empty'          => ['service_rates.0.line_name', null, 'A value is required here.'],
    'an unknown service rate'     => [
        'attributes.0.required_for',
        ['nope'],
        'There is no service rate named nope.',
    ],
]);

// Each order is wrapped in a list, so Pest passes the closure rather than calling it.
test('a pricing message fills every placeholder', function (array $price, string $text) {
    $failed = Messages::failure($price[0]);

    expect(Messages::english($failed->key, $failed->params))->toBe($text);
})->with([
    'a stored charge with a negative amount' => [
        [fn () => Orders::price(['manual' => [ManualChargeInput::added('hourly', 'HOURLY', 'Hourly charge', -1)]])],
        "The amount for Hourly charge isn't valid.",
    ],
    'an entered amount out of range'         => [
        [fn () => Orders::price(['manual' => [ManualChargeInput::new('entered', amount: 1)]])],
        'The amount for Entered charge must be from 8.00 to 150.00 USD.',
    ],
    'a quantity on a charge that has none'   => [
        [fn () => Orders::price([
            'profile' => Profiles::set(Profiles::example(), 'manual_charges.0.quantity', null),
            'manual'  => [ManualChargeInput::new('hourly', quantity: '1')],
        ])],
        "Hourly charge doesn't take a quantity.",
    ],
    'a quantity on an entered charge'        => [
        [fn () => Orders::price(['manual' => [ManualChargeInput::new('entered', quantity: '1', amount: 1000)]])],
        "Entered charge doesn't take a quantity.",
    ],
    'a quantity off its step'                => [
        [fn () => Orders::price(['manual' => [ManualChargeInput::new('hourly', quantity: '1.1')]])],
        'The quantity for Hourly charge must be in steps of 0.25, up to 24.',
    ],
    'a blank price on the rate card'         => [
        [fn () => Orders::price([
            'row'      => ['code' => 'R1', 'first' => 2700, 'second' => 1800, 'third' => 1200],
            'entities' => [Orders::entity(5, 'separate')],
        ])],
        'Separate entity type has no amount in separate_price, row R1 of Example customer\'s card Main.',
    ],
    'a blank tier limit on the rate card'    => [
        [fn () => Orders::price([
            'profile' => Profiles::set(
                Profiles::set(
                    Profiles::example(),
                    'rate_tables.0.columns.5',
                    ['key' => 'included', 'type' => 'integer'],
                ),
                'service_rates.0.tiers.0.up_to',
                ['column' => 'included'],
            ),
        ])],
        'Tiered delivery has no tier limit in included, row R1 of Example customer\'s card Main.',
    ],
    'a service rate turned off'              => [
        [fn () => Orders::price(['overrides' => ['service_rates.tiered.enabled' => false]])],
        'Tiered delivery is turned off.',
    ],
    'a service rate the customer may not use' => [
        [fn () => Orders::price(['overrides' => ['allowed_service_rates' => ['distance']]])],
        "Example customer can't use Tiered delivery.",
    ],
    'an unknown entity type'                 => [
        [fn () => Orders::price(['entities' => [Orders::entity(5, 'nope')]])],
        'Entity 1 has an unknown type, nope.',
    ],
    'an invalid profile'                     => [
        [fn () => Orders::price(['profile' => Profiles::set(Profiles::example(), 'currency', 'cad')])],
        "The pricing profile isn't valid. Problems found: 1.",
    ],
]);

test('invalid overrides and dated changes name the path at fault', function () {
    $overrides = Messages::failure(fn () => Orders::price(['overrides' => ['fees.nope.amount' => 100]]));
    $changes   = Messages::failure(fn () => Orders::price([
        'changes' => [['path' => 'fees.nope.amount', 'value' => 100, 'effective_from' => '2027-01-01']],
    ]));

    expect(Messages::english($overrides->key, $overrides->params))
        ->toBe("Example customer's overrides aren't valid at fees.nope.amount.")
        ->and(Messages::english($changes->key, $changes->params))
        ->toBe("The profile's dated change to fees.nope.amount isn't valid.");
});

test('too many overrides or dated changes name no path', function () {
    $overrides = [];
    for ($index = 0; $index < 201; $index++) {
        $overrides["fees.nope$index.amount"] = 1;
    }
    $failed = Messages::failure(fn () => Orders::price(['overrides' => $overrides]));

    expect(Messages::english($failed->key, $failed->params))->toBe("Example customer's overrides aren't valid.");
});

test('every type a value can be expected to have has English text', function () {
    $profile = Profiles::set(Profiles::example(), 'distance_unit', null);
    $profile = Profiles::set($profile, 'holiday_lists', null);

    try {
        (new ProfileValidator())->normalize($profile);
    } catch (InvalidProfile $invalid) {
        foreach ($invalid->problems as $problem) {
            expect(Messages::english($problem->key, $problem->params))->not->toContain('<no text');
        }

        return;
    }
    throw new RuntimeException('The profile was accepted.');
});

test('a profile made invalid by overrides and changes together is named by rule key', function () {
    $failed = Messages::failure(fn () => Orders::price([
        'overrides' => ['fees.heavy.maximum' => 3000],
        'changes'   => [['path' => 'fees.heavy.minimum', 'value' => 5000, 'effective_from' => '2027-01-01']],
    ]));

    expect($failed->key)->toBe('enhanced-pricing.pricing.profile-invalid-for-order')
        ->and($failed->params['path'])->toStartWith('fees.heavy.');
});

/**
 * The whole text keys the console's templates and code name, under enhanced-pricing.console.
 *
 * @return list<string>
 */
function consoleKeys(): array
{
    $keys  = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/../../addon'));
    foreach ($files as $file) {
        if ($file->isFile() && in_array($file->getExtension(), ['hbs', 'js'], true)) {
            $source = (string) file_get_contents($file->getPathname());
            preg_match_all('/enhanced-pricing\.console\.[a-z0-9.-]*+/', $source, $found);
            // A key ending in a dot is the start of one made in the template, checked below.
            $keys = [...$keys, ...array_filter($found[0], static fn (string $key) => !str_ends_with($key, '.'))];
        }
    }

    return array_values(array_unique($keys));
}

/**
 * @param array<string, mixed> $tree
 */
function hasText(array $tree, string $key): bool
{
    $value = $tree;
    foreach (explode('.', $key) as $part) {
        if (is_array($value) === false || array_key_exists($part, $value) === false) {
            return false;
        }
        $value = $value[$part];
    }

    return is_string($value);
}

test('every console text has English text, including each section, variant and choice', function () {
    $all  = ['enhanced-pricing' => Messages::translations()];
    $spec = ProfileForm::spec();
    $keys = consoleKeys();
    foreach ($spec['sections'] as $section) {
        $keys[] = 'enhanced-pricing.console.section.' . $section['key'];
    }
    foreach ($spec['kinds'] as $kind) {
        foreach (array_keys($kind['variants'] ?? []) as $variant) {
            $keys[] = "enhanced-pricing.console.variant.$variant";
        }
        foreach ($kind['fields'] as $field) {
            foreach (array_keys($field['choices'] ?? []) as $choice) {
                $keys[] = "enhanced-pricing.console.choice.$choice";
            }
        }
        foreach ($kind['variants'] ?? [] as $fields) {
            foreach ($fields as $field) {
                foreach (array_keys($field['choices'] ?? []) as $choice) {
                    $keys[] = "enhanced-pricing.console.choice.$choice";
                }
            }
        }
    }
    foreach (['upcoming', 'in-effect', 'stale', 'cancelled'] as $state) {
        $keys[] = "enhanced-pricing.console.change-state.$state";
    }
    foreach (['customer', 'row', 'dated_change'] as $kind) {
        $keys[] = "enhanced-pricing.console.stale-kind.$kind";
    }
    foreach (range(1, 7) as $day) {
        $keys[] = "enhanced-pricing.console.weekday.$day";
    }
    foreach (['length', 'width', 'height'] as $side) {
        $keys[] = "enhanced-pricing.console.preview.$side";
    }

    expect(count($keys))->toBeGreaterThan(150);
    foreach (array_unique($keys) as $key) {
        expect(hasText($all, $key))->toBeTrue("$key has no English text");
    }
});
