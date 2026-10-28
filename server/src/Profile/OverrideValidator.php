<?php

namespace Fleetbase\EnhancedPricing\Profile;

/**
 * Checks a customer's overrides against a profile: every path must name a value the profile has
 * and can override, every value must have the right type, and the profile with all of them
 * applied must still pass every check.
 */
final class OverrideValidator
{
    public const MAX_OVERRIDES = 200;

    /**
     * @param array<string, mixed> $profile   the profile as saved
     * @param array<mixed>         $overrides path => value
     *
     * @return list<Problem>
     */
    public function check(array $profile, array $overrides): array
    {
        try {
            $normalized = (new ProfileValidator())->normalize($profile);
        } catch (InvalidProfile $invalid) {
            return $invalid->problems;
        }
        if (count($overrides) > self::MAX_OVERRIDES) {
            return [Problem::atPath('', 'too-many', ['max' => self::MAX_OVERRIDES])];
        }

        $schema   = new Schema();
        $merged   = $normalized;
        $pointers = [];
        foreach ($overrides as $path => $value) {
            $path  = (string) $path;
            $found = OverridePaths::find($normalized, $path);
            if ($found === null) {
                $schema->add(OverridePaths::unknown($normalized, $path, $path));
                continue;
            }
            $before = count($schema->problems());
            $value  = ($found['check'])($schema, $value, $path);
            if (count($schema->problems()) !== $before) {
                continue;
            }
            if ($found['pointer'] === null) {
                $this->allowedServiceRates($schema, $normalized, $value);
                continue;
            }
            $merged                                    = OverridePaths::apply($merged, $found['pointer'], $value);
            $pointers[implode('.', $found['pointer'])] = $path;
        }

        foreach ((new ProfileValidator())->recheck($merged) as $problem) {
            $schema->add(new Problem(self::attribute($problem->path, $pointers), $problem->key, $problem->params));
        }

        return $schema->problems();
    }

    /**
     * @param array<string, mixed> $profile
     */
    private function allowedServiceRates(Schema $schema, array $profile, mixed $keys): void
    {
        $known = array_map(
            static fn (array $rate): mixed => $rate['key'],
            ProfileReferences::items($profile['service_rates']),
        );
        foreach (ProfileReferences::strings($keys) as $key) {
            if (in_array($key, $known, true) === false) {
                $schema->add(Problem::atPath(
                    OverridePaths::ALLOWED_SERVICE_RATES,
                    'unknown-reference',
                    ['kind' => 'service_rate', 'key' => $key],
                ));
            }
        }
    }

    /**
     * Names a problem in the merged profile by the override that caused it: the override whose
     * value the problem is in, else one on the same rule, else the whole set.
     *
     * @param array<string, string> $pointers profile pointer => override path
     */
    public static function attribute(string $problemPath, array $pointers): string
    {
        foreach ($pointers as $pointer => $path) {
            if ($problemPath === $pointer || str_starts_with($problemPath, $pointer . '.')) {
                return $path;
            }
        }
        $rule = implode('.', array_slice(explode('.', $problemPath), 0, 2));
        foreach ($pointers as $pointer => $path) {
            if (str_starts_with($pointer, $rule . '.')) {
                return $path;
            }
        }

        return '';
    }
}
