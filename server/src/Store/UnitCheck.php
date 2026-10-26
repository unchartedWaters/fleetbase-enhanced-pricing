<?php

namespace Fleetbase\EnhancedPricing\Store;

use Fleetbase\EnhancedPricing\Models\DatedChange;
use Fleetbase\EnhancedPricing\Models\Profile;
use Fleetbase\EnhancedPricing\Profile\OverridePaths;
use Fleetbase\EnhancedPricing\Profile\Problem;
use Fleetbase\EnhancedPricing\Profile\ProfileReferences as R;

/**
 * What a new profile version may not change under stored values: the profile's currency and
 * distance unit; the unit of a rule that an override or a live dated change names, since no unit
 * is stored with the value; and the undated value under a dated change already in effect, since
 * the change would keep winning.
 */
final class UnitCheck
{
    /**
     * @param array<string, mixed> $new a normalized profile
     *
     * @return list<Problem>
     */
    public static function fixed(Profile $profile, array $new): array
    {
        $problems = [];
        foreach (['currency' => $profile->currency, 'distance_unit' => $profile->distance_unit] as $field => $value) {
            if (($new[$field] ?? null) !== $value) {
                $params     = ['field' => $field, 'value' => $value];
                $problems[] = StoreRefused::problem('fixed-unit-changed', $params, $field);
            }
        }

        return $problems;
    }

    /**
     * @param array<string, mixed> $old   normalized
     * @param array<string, mixed> $new   normalized
     * @param list<string>         $paths override and dated change paths
     *
     * @return list<Problem>
     */
    public static function named(array $old, array $new, array $paths): array
    {
        $problems = [];
        $rules    = [];
        foreach ($paths as $path) {
            $parts = explode('.', $path);
            if (count($parts) >= 3) {
                $rules[$parts[0] . '.' . $parts[1]] = [$parts[0], $parts[1]];
            }
        }
        foreach ($rules as $rule => [$section, $key]) {
            $before = self::unit($old, $section, $key);
            $after  = self::unit($new, $section, $key);
            if ($before !== null && $after !== null && $before !== $after) {
                $problems[] = StoreRefused::problem('unit-changed', ['rule' => $rule], $rule);
            }
        }

        return $problems;
    }

    /**
     * @param array<string, mixed>  $old     normalized
     * @param array<string, mixed>  $new     normalized
     * @param iterable<DatedChange> $changes live changes
     *
     * @return list<Problem>
     */
    public static function inEffect(array $old, array $new, iterable $changes, string $today): array
    {
        $problems = [];
        foreach ($changes as $change) {
            if ($change->effective_from > $today) {
                continue;
            }
            $before = self::valueAt($old, $change->path);
            $after  = self::valueAt($new, $change->path);
            if ($before !== null && $after !== null && $before !== $after) {
                $path       = $change->path;
                $problems[] = StoreRefused::problem('value-has-change-in-effect', ['path' => $path], $path);
            }
        }

        return $problems;
    }

    /**
     * The units a rule's stored values are priced in, as JSON, or null when the profile has no
     * such rule.
     *
     * @param array<string, mixed> $profile
     */
    private static function unit(array $profile, string $section, string $key): ?string
    {
        $rule = null;
        foreach (R::items($profile[$section] ?? []) as $item) {
            if ($item['key'] === $key) {
                $rule = $item;
            }
        }
        if ($rule === null) {
            return null;
        }
        $kind     = $section === 'fees' ? ($rule['kind'] ?? null) : $section;
        $quantity = R::map($rule['quantity'] ?? null);
        $units    = match (true) {
            $kind === 'manual_charges'                            => [$quantity['unit'] ?? null],
            $kind === 'parcel'                                    => [$rule['basis'] ?? null, $rule['unit'] ?? null],
            $section === 'fees' && $rule['charge'] === 'per_unit' => [self::attributeUnit($profile, $rule)],
            default                                               => [],
        };

        return (string) json_encode($units);
    }

    /**
     * @param array<string, mixed> $profile
     * @param array<string, mixed> $rule
     */
    private static function attributeUnit(array $profile, array $rule): mixed
    {
        foreach (R::items($profile['attributes'] ?? []) as $attribute) {
            if ($attribute['key'] === ($rule['attribute'] ?? null)) {
                return $attribute['unit'] ?? null;
            }
        }

        return null;
    }

    /**
     * The value at an override path, as JSON, or null when the profile has no such value.
     *
     * @param array<string, mixed> $profile
     */
    private static function valueAt(array $profile, string $path): ?string
    {
        $pointer = OverridePaths::find($profile, $path)['pointer'] ?? null;
        if ($pointer === null) {
            return null;
        }
        $cursor = $profile;
        foreach ($pointer as $part) {
            $cursor = is_array($cursor) ? ($cursor[$part] ?? null) : null;
        }

        return (string) json_encode($cursor);
    }
}
