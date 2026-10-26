<?php

use Fleetbase\EnhancedPricing\Pricing\ManualChargeInput;
use Fleetbase\EnhancedPricing\Pricing\PricingFailed;
use Fleetbase\EnhancedPricing\Profile\InvalidProfile;
use Fleetbase\EnhancedPricing\Profile\Problem;
use Fleetbase\EnhancedPricing\Profile\ProfileValidator;
use Fleetbase\EnhancedPricing\Tests\Support\Orders;
use Fleetbase\EnhancedPricing\Tests\Support\Profiles;
use Symfony\Component\Yaml\Yaml;

// The messages people see: every key has English text, every placeholder in that text gets a
// value, and each message names the right cause.

/**
 * @return array<string, mixed>
 */
function translations(): array
{
    $parsed = Yaml::parseFile(__DIR__ . '/../../translations/en-us.yaml');

    return is_array($parsed['enhanced-pricing'] ?? null) ? $parsed['enhanced-pricing'] : [];
}

/**
 * The English text of a key with its parameters filled in. Parameters that are type or kind
 * codes are shown with their own English text, as the console does.
 *
 * @param array<string, scalar|null> $params
 */
function english(string $key, array $params): string
{
    $all     = translations();
    $section = str_starts_with($key, 'enhanced-pricing.profile.') ? 'profile' : 'pricing';
    $name    = substr($key, strlen("enhanced-pricing.$section."));
    $text    = $all[$section][$name] ?? null;
    expect($text)->toBeString("$key has no English text");
    foreach ($params as $param => $value) {
        $shown = (string) $value;
        if ($param === 'expected') {
            $shown = $all['types'][$shown] ?? "<no text for type $shown>";
        }
        if ($param === 'kind') {
            $shown = $all['kinds'][$shown] ?? "<no text for kind $shown>";
        }
        $text = str_replace('{' . $param . '}', $shown, (string) $text);
    }

    return (string) $text;
}

function failure(callable $price): PricingFailed
{
    try {
        $price();
    } catch (PricingFailed $failed) {
        return $failed;
    }
    throw new RuntimeException('Pricing did not stop.');
}

/**
 * The literal keys passed to a call of one of these functions, however deeply its arguments
 * nest.
 *
 * @param list<string> $functions
 *
 * @return list<string>
 */
function keysPassedTo(array $functions): array
{
    $keys = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/../src')) as $file) {
        if ($file->isFile() === false) {
            continue;
        }
        $tokens = array_values(array_filter(
            token_get_all((string) file_get_contents($file->getPathname())),
            static fn ($token) => is_array($token) === false || in_array($token[0], [T_WHITESPACE, T_COMMENT], true) === false,
        ));
        foreach ($tokens as $at => $token) {
            if (is_array($token) === false || in_array($token[1], $functions, true) === false || ($tokens[$at + 1] ?? null) !== '(') {
                continue;
            }
            $depth = 0;
            for ($next = $at + 1; $next < count($tokens); $next++) {
                $part  = $tokens[$next];
                $depth += $part === '(' ? 1 : ($part === ')' ? -1 : 0);
                if ($depth === 0) {
                    break;
                }
                if (is_array($part) && $part[0] === T_CONSTANT_ENCAPSED_STRING) {
                    $value = trim($part[1], "'\"");
                    if (preg_match('/^[a-z]+(-[a-z]+)+$/', $value) === 1) {
                        $keys[] = $value;
                    }
                }
            }
        }
    }

    return array_values(array_unique($keys));
}

test('every problem and pricing key has English text, however its call is written', function () {
    $all      = translations();
    $problems = keysPassedTo(['atPath']);
    $pricing  = keysPassedTo(['fail', 'because', 'note']);

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

test('a misspelt field is named in the message', function () {
    $profile = Profiles::set(Profiles::example(), 'entity_types.1.priced', true);

    $problems = (new ProfileValidator())->check($profile);
    $unknown  = array_values(array_filter($problems, static fn (Problem $problem) => str_ends_with($problem->key, 'unknown-field')));

    expect($unknown)->toHaveCount(1)
        ->and(english($unknown[0]->key, $unknown[0]->params))->toBe("priced isn't a field here.");
});

test('profile problems fill every placeholder, with types and kinds in plain words', function (string $path, mixed $value, string $text) {
    $profile = Profiles::set(Profiles::example(), $path, $value);

    try {
        (new ProfileValidator())->normalize($profile);
    } catch (InvalidProfile $invalid) {
        $texts = array_map(static fn (Problem $problem) => english($problem->key, $problem->params), $invalid->problems);

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
    'money as text'                => ['service_rates.1.per_meter.rate', 'x', 'The value must be a whole number of cents.'],
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
    $failed = failure($price[0]);

    expect(english($failed->key, $failed->params))->toBe($text);
})->with([
    'a stored charge with a negative amount' => [
        [fn () => Orders::price(['manual' => [ManualChargeInput::added('hourly', 'HOURLY', 'Hourly charge', -1)]])],
        "The amount for Hourly charge isn't valid.",
    ],
    'an entered amount out of range'         => [
        [fn () => Orders::price(['manual' => [ManualChargeInput::new('entered', amount: 1)]])],
        'The amount for Entered charge must be from 800 to 15000 cents.',
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
                Profiles::set(Profiles::example(), 'rate_tables.0.columns.5', ['key' => 'included', 'type' => 'integer']),
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
        "Tiered delivery isn't allowed for Example customer.",
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
    $overrides = failure(fn () => Orders::price(['overrides' => ['fees.nope.amount' => 100]]));
    $changes   = failure(fn () => Orders::price([
        'changes' => [['path' => 'fees.nope.amount', 'value' => 100, 'effective_from' => '2027-01-01']],
    ]));

    expect(english($overrides->key, $overrides->params))
        ->toBe("Example customer's overrides aren't valid at fees.nope.amount.")
        ->and(english($changes->key, $changes->params))
        ->toBe("The profile's dated change to fees.nope.amount isn't valid.");
});

test('too many overrides or dated changes name no path', function () {
    $overrides = [];
    for ($index = 0; $index < 201; $index++) {
        $overrides["fees.nope$index.amount"] = 1;
    }
    $failed = failure(fn () => Orders::price(['overrides' => $overrides]));

    expect(english($failed->key, $failed->params))->toBe("Example customer's overrides aren't valid.");
});

test('every type a value can be expected to have has English text', function () {
    $profile = Profiles::set(Profiles::example(), 'distance_unit', null);
    $profile = Profiles::set($profile, 'holiday_lists', null);

    try {
        (new ProfileValidator())->normalize($profile);
    } catch (InvalidProfile $invalid) {
        foreach ($invalid->problems as $problem) {
            expect(english($problem->key, $problem->params))->not->toContain('<no text');
        }

        return;
    }
    throw new RuntimeException('The profile was accepted.');
});

test('a profile made invalid by overrides and changes together is named by rule key', function () {
    $failed = failure(fn () => Orders::price([
        'overrides' => ['fees.heavy.maximum' => 3000],
        'changes'   => [['path' => 'fees.heavy.minimum', 'value' => 5000, 'effective_from' => '2027-01-01']],
    ]));

    expect($failed->key)->toBe('enhanced-pricing.pricing.profile-invalid-for-order')
        ->and($failed->params['path'])->toStartWith('fees.heavy.');
});
