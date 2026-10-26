<?php

namespace Fleetbase\EnhancedPricing\Pricing;

use Fleetbase\EnhancedPricing\Profile\ChangeValidator;
use Fleetbase\EnhancedPricing\Profile\InvalidProfile;
use Fleetbase\EnhancedPricing\Profile\OverridePaths;
use Fleetbase\EnhancedPricing\Profile\OverrideValidator;
use Fleetbase\EnhancedPricing\Profile\ProfileResolver;
use Fleetbase\EnhancedPricing\Profile\ProfileValidator;

/**
 * Checks a pricing request's profile, overrides, dated changes and timezone, then resolves the
 * profile for the pickup's local date and checks the result again. The first problem stops
 * pricing.
 */
final class RequestResolver
{
    /**
     * @return array{
     *     profile: array<string, mixed>,
     *     allowed: list<string>|null,
     *     changes: array<string, string>,
     *     local: \DateTimeImmutable,
     * }
     *
     * @throws PricingFailed
     */
    public function resolve(PricingRequest $request): array
    {
        [$profile, $local] = $this->checkRequest($request);
        $resolved          = (new ProfileResolver())->resolve(
            $profile,
            $request->overrides,
            $request->valueChanges,
            $local->format('Y-m-d'),
        );
        $problems          = (new ProfileValidator())->recheck($resolved['profile']);
        if ($problems !== []) {
            throw PricingFailed::because('profile-invalid-for-order', ['path' => OverridePaths::keyed($resolved['profile'], $problems[0]->path)]);
        }

        return [
            'profile' => $resolved['profile'],
            'allowed' => $resolved['allowed'],
            'changes' => $resolved['changes'],
            'local'   => $local,
        ];
    }

    /**
     * Checks the profile, overrides, changes and timezone, and returns the normalized profile and
     * the pickup's local time.
     *
     * @return array{array<string, mixed>, \DateTimeImmutable}
     */
    private function checkRequest(PricingRequest $request): array
    {
        try {
            $profile = (new ProfileValidator())->normalize($request->profile);
        } catch (InvalidProfile $invalid) {
            throw PricingFailed::because('profile-invalid', ['problems' => count($invalid->problems)]);
        }
        $problems = (new OverrideValidator())->check($request->profile, $request->overrides);
        if ($problems !== []) {
            throw PricingFailed::because('overrides-invalid', ['path'     => $problems[0]->path, 'customer' => $request->labels['customer'] ?? '']);
        }
        $problems = (new ChangeValidator())->checkEach($request->profile, $request->valueChanges);
        if ($problems !== []) {
            throw PricingFailed::because('changes-invalid', ['path' => $problems[0]->path]);
        }
        try {
            return [$profile, $request->pickupAt->setTimezone(new \DateTimeZone($request->timezone))];
        } catch (\Exception) {
            throw PricingFailed::because('timezone-invalid', ['timezone' => $request->timezone]);
        }
    }
}
