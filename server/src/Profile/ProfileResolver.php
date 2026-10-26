<?php

namespace Fleetbase\EnhancedPricing\Profile;

/**
 * The profile one order is priced with: the profile's values, then the dated changes in effect on
 * the pricing date, then the customer's overrides, which always win. The overrides and changes
 * must already have passed their checks.
 */
final class ProfileResolver
{
    /**
     * @param array<string, mixed>                                            $profile   normalized
     * @param array<mixed>                                                    $overrides path => value
     * @param list<array{path: string, value: mixed, effective_from: string}> $changes
     *
     * @return array{
     *     profile: array<string, mixed>,
     *     allowed: list<string>|null,
     *     overrides: array<string, mixed>,
     *     changes: array<string, string>,
     * }
     */
    public function resolve(array $profile, array $overrides, array $changes, string $date): array
    {
        $resolved = $profile;
        $latest   = [];
        foreach ($changes as $change) {
            $path = $change['path'];
            if (array_key_exists($path, $overrides) || $change['effective_from'] > $date) {
                continue;
            }
            if (isset($latest[$path]) === false || $change['effective_from'] > $latest[$path]['effective_from']) {
                $latest[$path] = $change;
            }
        }

        $applied = [];
        foreach ($latest as $path => $change) {
            $resolved       = $this->set($profile, $resolved, $path, $change['value']);
            $applied[$path] = $change['effective_from'];
        }

        $allowed = null;
        foreach ($overrides as $path => $value) {
            if ($path === OverridePaths::ALLOWED_SERVICE_RATES) {
                $allowed = ProfileReferences::strings($value);
                continue;
            }
            $resolved = $this->set($profile, $resolved, (string) $path, $value);
        }

        return [
            'profile'   => $resolved,
            'allowed'   => $allowed,
            'overrides' => ProfileReferences::map($overrides),
            'changes'   => $applied,
        ];
    }

    /**
     * Sets one checked value, normalized the same way as the profile's own values.
     *
     * @param array<string, mixed> $profile
     * @param array<string, mixed> $resolved
     *
     * @return array<string, mixed>
     */
    private function set(array $profile, array $resolved, string $path, mixed $value): array
    {
        $found = OverridePaths::find($profile, $path);
        if ($found === null || $found['pointer'] === null) {
            return $resolved;
        }

        return OverridePaths::apply($resolved, $found['pointer'], ($found['check'])(new Schema(), $value, $path));
    }
}
