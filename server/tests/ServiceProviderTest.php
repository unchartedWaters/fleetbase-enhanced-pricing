<?php

use Fleetbase\EnhancedPricing\Providers\EnhancedPricingServiceProvider;
use Fleetbase\Providers\CoreServiceProvider;

// Laravel loads the provider named in composer.json; a missing class stops the whole host app.
test('composer.json names the extension provider', function () {
    $composer = json_decode((string) file_get_contents(__DIR__ . '/../../composer.json'), true);

    expect($composer['extra']['laravel']['providers'])->toBe([EnhancedPricingServiceProvider::class])
        ->and(is_subclass_of(EnhancedPricingServiceProvider::class, CoreServiceProvider::class))->toBeTrue();
});

test('the extension does not inherit the core observer list', function () {
    $defaults = (new ReflectionClass(EnhancedPricingServiceProvider::class))->getDefaultProperties();

    expect($defaults['observers'])->toBe([]);
});

// Fleetbase v0.8 ships core-api 2.x (Laravel 12); a constraint without it stops Composer installing us.
test('composer.json accepts the core-api 1.6 and 2.x lines', function (string $version) {
    $composer = json_decode((string) file_get_contents(__DIR__ . '/../../composer.json'), true);

    expect(Composer\Semver\Semver::satisfies($version, $composer['require']['fleetbase/core-api']))->toBeTrue();
})->with(['1.6.0', '1.6.74', '2.0.2', '2.5.0']);
