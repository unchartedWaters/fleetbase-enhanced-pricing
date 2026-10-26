<?php

use Fleetbase\EnhancedPricing\Auth\Schemas\EnhancedPricing;
use Fleetbase\EnhancedPricing\Support\Authorizer;

// Fleetbase checks permissions only for resource controllers, so every console controller asks
// the authorizer itself. Portal users are refused on every console route; administrators pass.

// Decision 21's four permissions, spelled as Fleetbase's three-word "service action resource":
// the console's abilities read only three words, and schema policies refuse other names.
test('the permission schema makes the four permissions', function () {
    $schema = new EnhancedPricing();

    expect($schema->name)->toBe('enhanced-pricing')
        ->and($schema->permissions)->toBe(['manage pricing-profile', 'manage rate-card', 'manage manual-charge'])
        ->and($schema->resources)->toBe([])
        ->and($schema->guards)->toBe(['sanctum']);
    foreach ($schema->policies as $policy) {
        foreach ($policy['permissions'] as $permission) {
            expect(count(explode(' ', "enhanced-pricing $permission")))->toBe(3);
        }
    }
    expect(array_column($schema->policies, 'name'))->toBe(['EnhancedPricingManager', 'EnhancedPricingRateCards']);
});

test('the authorizer decides by user type and held permissions', function (
    ?string $type,
    array $held,
    array $needed,
    bool $allowed,
) {
    expect(Authorizer::allows($type, $held, $needed))->toBe($allowed);
})->with([
    'no user'                         => [null, [], [Authorizer::SEE], false],
    'a portal user, even with rights' => ['customer', ['enhanced-pricing *'], [Authorizer::SEE], false],
    'an administrator'                => ['admin', [], [Authorizer::PROFILES], true],
    'a user with nothing'             => ['user', [], [Authorizer::SEE], false],
    'see extension to see'            => ['user', ['enhanced-pricing see extension'], [Authorizer::SEE], true],
    'see extension is not manage'     => ['user', ['enhanced-pricing see extension'], [Authorizer::PROFILES], false],
    'the wildcard'                    => ['user', ['enhanced-pricing *'], [Authorizer::PROFILES], true],
    'one of the needed'               => [
        'user',
        ['enhanced-pricing manage rate-card'],
        [Authorizer::PROFILES, Authorizer::RATE_CARDS],
        true,
    ],
    'a manage permission implies see' => ['user', ['enhanced-pricing manage rate-card'], [Authorizer::SEE], true],
    'manual charges is not profiles'  => [
        'user',
        ['enhanced-pricing manage manual-charge'],
        [Authorizer::PROFILES],
        false,
    ],
    'another extension\'s wildcard'   => ['user', ['fleet-ops *'], [Authorizer::SEE], false],
    'a driver'                        => ['driver', ['enhanced-pricing *'], [Authorizer::SEE], false],
    'a contact'                       => ['contact', ['enhanced-pricing *'], [Authorizer::SEE], false],
    'nothing needed is still refused' => ['user', ['enhanced-pricing *'], [], false],
]);
