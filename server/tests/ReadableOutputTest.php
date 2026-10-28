<?php

use Fleetbase\EnhancedPricing\Pricing\EntityInput;
use Fleetbase\EnhancedPricing\Pricing\LineBook;
use Fleetbase\EnhancedPricing\Pricing\ManualChargeInput;
use Fleetbase\EnhancedPricing\Profile\ChangeValidator;
use Fleetbase\EnhancedPricing\Profile\OverrideValidator;
use Fleetbase\EnhancedPricing\Profile\ProfileValidator;
use Fleetbase\EnhancedPricing\Tests\Support\Messages;
use Fleetbase\EnhancedPricing\Tests\Support\Orders;
use Fleetbase\EnhancedPricing\Tests\Support\Profiles;

// What staff and invoice readers see: messages that say what to fix, in amounts rather than
// cents, and invoice lines that say what they charge for. From the UX review of 2026-10-26.

beforeEach(function () {
    $this->failure = function (array $options): string {
        $failed = Messages::failure(fn () => Orders::price($options));

        return Messages::english($failed->key, $failed->params);
    };
});

test('an amount sent with a charge whose amount is set says so', function () {
    expect(($this->failure)(['manual' => [ManualChargeInput::new('hourly', quantity: '1', amount: 1000)]]))
        ->toBe("Hourly charge has a set amount, so an amount can't be entered.");
});

test('an entered charge sent with no amount asks for one', function () {
    $failed = Messages::failure(fn () => Orders::price(['manual' => [ManualChargeInput::new('entered')]]));

    expect($failed->key)->toBe('enhanced-pricing.pricing.manual-charge-amount-required')
        ->and(Messages::english($failed->key, $failed->params))->toBe('Entered charge needs an amount.');
});

test('money in pricing messages is an amount in the profile currency, not cents', function () {
    expect(($this->failure)(['manual' => [ManualChargeInput::new('entered', amount: 1)]]))
        ->toBe('The amount for Entered charge must be from 8.00 to 150.00 USD.')
        ->and(($this->failure)([
            'service_rate'    => 'distance',
            'overrides'       => ['service_rates.distance.per_meter.rate' => 100000000],
            'distance_meters' => 900000,
        ]))->toBe('A line or the total is above the largest amount, 1000000.00 USD.');
});

test('no message ends with a customer name, which may end in a full stop', function () {
    $texts        = [];
    $translations = Messages::translations();
    array_walk_recursive($translations, function (mixed $text) use (&$texts) {
        $texts[] = $text;
    });

    foreach ($texts as $text) {
        expect($text)->not->toMatch('/\{customer\}\.$/');
    }
    expect(($this->failure)([
        'overrides' => ['allowed_service_rates' => ['distance']],
        'labels'    => ['customer' => 'Example Supplies Ltd.', 'card' => 'Main', 'row' => 'R1'],
    ]))->toBe("Example Supplies Ltd. can't use Tiered delivery.");
});

test('a missing amount names the customer first', function () {
    expect(($this->failure)(['attributes' => ['extra' => true], 'overrides' => ['fees.extra_handling.amount' => null]]))
        ->toBe('Example customer has no amount set for Additional fee.');
});

test('distance messages name the service rate and the unit', function () {
    expect(($this->failure)(['service_rate' => 'distance']))
        ->toBe("Distance delivery needs the order's distance.")
        ->and(($this->failure)(['service_rate' => 'distance', 'distance_meters' => 1000500]))
        ->toBe('The distance 1000.5 km is above the maximum of 1000 km.');
});

test('an override for a rule the profile does not have names the rule', function (string $path, string $text) {
    $overrides = Messages::problems((new OverrideValidator())->check(Profiles::example(), [$path => 1]));
    $changes   = Messages::problems((new ChangeValidator())->check(
        Profiles::example(),
        [['path' => $path, 'value' => 1, 'effective_from' => '2027-01-01']],
    ));

    expect($overrides)->toBe([$text])->and($changes)->toBe([$text]);
})->with([
    'a fee'           => ['fees.overwieght.thresholds', 'There is no fee named overwieght.'],
    'a subtotal fee'  => ['subtotal_fees.nope.amount', 'There is no subtotal fee named nope.'],
    'a service rate'  => ['service_rates.nope.minimum', 'There is no service rate named nope.'],
    'a manual charge' => ['manual_charges.nope.amount', 'There is no manual charge named nope.'],
]);

test('a field that can never be overridden keeps its own message', function () {
    $problems = (new OverrideValidator())->check(
        Profiles::example(),
        ['service_rates.distance.per_meter.maximum_distance' => 5000],
    );

    expect(Messages::problems($problems))
        ->toBe(["service_rates.distance.per_meter.maximum_distance isn't a value that can be overridden."]);
});

test('a rate table column can have a name, which messages use', function () {
    $profile = Profiles::set(Profiles::example(), 'rate_tables.0.columns.4.name', 'Separate price');

    expect((new ProfileValidator())->check($profile))->toBe([])
        ->and(($this->failure)([
            'profile'  => $profile,
            'row'      => ['code' => 'R1', 'first' => 2700, 'second' => 1800, 'third' => 1200],
            'entities' => [Orders::entity(5, 'separate')],
        ]))->toBe("Separate entity type has no amount in Separate price, row R1 of Example customer's card Main.");
});

test('an entity is named by its label when the caller gives one', function () {
    expect(($this->failure)(['entities' => [new EntityInput(type: 'nope', label: 'PKG-7')]]))
        ->toBe('Entity PKG-7 has an unknown type, nope.');
});

test('entities a fee could not measure are noted once per fee', function () {
    $quote = Orders::price(['entities' => [
        new EntityInput(label: 'PKG-1'),
        Orders::entity(5),
        new EntityInput(label: 'PKG-3'),
    ]]);
    $notes = array_map(static fn (array $note) => Messages::english($note['key'], $note['params']), $quote->notes);

    expect($notes)->toBe(["Parcel fee wasn't charged for entities with no weight or size: PKG-1, PKG-3."]);
});

test('a percentage line shows its percentage and what it is a percentage of', function () {
    $quote = Orders::price([
        'service_rate'    => 'distance',
        'entities'        => [Orders::entity(42)],
        'distance_meters' => 7000,
    ]);

    expect(Orders::lines($quote)[2])->toBe(['PERCENT', 'Percentage subtotal fee (11% of 4.80)', 1, 53, 53]);
});

test('a percentage raised to its minimum is named by the fee alone', function () {
    $quote = Orders::price([
        'service_rate'    => 'distance',
        'entities'        => [Orders::entity(42)],
        'distance_meters' => 7000,
        'overrides'       => ['subtotal_fees.percent.minimum' => 100],
    ]);

    expect(Orders::lines($quote)[2])->toBe(['PERCENT', 'Percentage subtotal fee', 1, 100, 100]);
});

test('a line name can join a text and a column', function () {
    $profile = Profiles::set(Profiles::example(), 'service_rates.0.line_name', ['text' => 'Route', 'column' => 'code']);

    expect(Orders::lines(Orders::price(['profile' => $profile]))[0][1])->toBe('Route: R1');
});

test('a line name needs a text or a column', function () {
    $profile = Profiles::set(Profiles::example(), 'service_rates.0.line_name', []);

    expect(Messages::problems((new ProfileValidator())->check($profile)))
        ->toContain('Give at least one of: text, column.');
});

test('a tier can have a name, shown on its line', function () {
    $profile = Profiles::set(Profiles::example(), 'service_rates.0.tiers.0.name', 'first two');
    $profile = Profiles::set($profile, 'service_rates.0.tiers.1.name', 'next three');
    $quote   = Orders::price(['profile' => $profile, 'entities' => array_fill(0, 3, Orders::entity(5))]);

    expect(array_slice(Orders::lines($quote), 0, 2))->toBe([
        ['TIERED', 'Tiered delivery (first two)', 2, 2700, 5400],
        ['TIERED', 'Tiered delivery (next three)', 1, 1800, 1800],
    ]);
});

test('tier names can come with a tiers override', function () {
    $tiers = [
        ['up_to' => ['value' => 1], 'price' => ['column' => 'first'], 'name' => 'first'],
        ['up_to' => null, 'price' => ['value' => 1500], 'name' => 'each extra'],
    ];

    expect((new OverrideValidator())->check(Profiles::example(), ['service_rates.tiered.tiers' => $tiers]))->toBe([]);
});

test('a line name longer than an invoice line allows is shortened', function () {
    $profile = Profiles::set(Profiles::example(), 'subtotal_fees.0.name', str_repeat('Fuel ', 40));
    $name    = Orders::lines(Orders::price(['profile' => $profile]))[1][1];

    expect(mb_strlen($name))->toBe(LineBook::MAX_NAME)
        ->and($name)->toEndWith('…')
        ->and($name)->toStartWith('Fuel Fuel');
});

test('an entity with a blank label is named by its position', function () {
    expect(($this->failure)(['entities' => [new EntityInput(type: 'nope', label: ' ')]]))
        ->toBe('Entity 1 has an unknown type, nope.');
});
