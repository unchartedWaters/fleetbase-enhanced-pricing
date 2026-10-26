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
            $request->datedChanges,
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
            $params = ['customer' => $request->labels['customer'] ?? ''];
            if ($problems[0]->path === '') {
                throw PricingFailed::because('overrides-invalid-all', $params);
            }
            throw PricingFailed::because('overrides-invalid', ['path' => $problems[0]->path] + $params);
        }
        $problems = (new ChangeValidator())->checkEach($request->profile, $request->datedChanges);
        if ($problems !== []) {
            $path = $this->changePath($request, $problems[0]->path);
            if ($path === '') {
                throw PricingFailed::because('changes-invalid-all');
            }
            throw PricingFailed::because('changes-invalid', ['path' => $path]);
        }
        try {
            return [$profile, $request->pickupAt->setTimezone(new \DateTimeZone($request->timezone))];
        } catch (\Exception) {
            throw PricingFailed::because('timezone-invalid', ['timezone' => $request->timezone]);
        }
    }

    /**
     * The path a refused dated change names, rather than its place in the list: a problem at
     * "2.value" is about the third change, whose path may be "fees.heavy.minimum".
     */
    private function changePath(PricingRequest $request, string $problemPath): string
    {
        $index = explode('.', $problemPath)[0];
        if (ctype_digit($index) === false) {
            return $problemPath;
        }
        $path = $request->datedChanges[(int) $index]['path'] ?? null;

        return is_string($path) ? $path : $problemPath;
    }
}
