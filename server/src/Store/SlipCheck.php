<?php

namespace Fleetbase\EnhancedPricing\Store;

use Fleetbase\EnhancedPricing\Profile\OverridePaths;

/**
 * Catches unit slips in overrides and dated changes: money is in minor units and percentages in
 * basis points, so 31 meant as 31% is 0.31%. A new money or percent value ten or more times above or
 * below the value it replaces is refused until the caller accepts it. Money inside a value, such
 * as a fixed rate's { "value": amount } or an entered charge's limits, is compared field by field.
 * A value of 0 or null, a list (tiers, thresholds), and a value already stored are not checked.
 */
final class SlipCheck
{
    public const RATIO = 10;

    private const FIELDS = ['amount', 'percentage', 'minimum', 'maximum', 'rate', 'base_fee', 'value'];

    /**
     * @param array<string, mixed> $profile  the current version, normalized
     * @param array<mixed>         $values   path => new value
     * @param array<mixed>         $replaced path => value it replaces, compared instead of the
     *                                       profile's; the same value again isn't checked
     *
     * @throws StoreRefused
     */
    public static function run(array $profile, array $values, array $replaced = []): void
    {
        $problems = [];
        foreach ($values as $path => $value) {
            $path = (string) $path;
            if (array_key_exists($path, $replaced) && Canonical::json($replaced[$path]) === Canonical::json($value)) {
                continue;
            }
            $current = $replaced[$path] ?? self::current($profile, $path);
            foreach (self::slips($current, $value, self::field($path)) as [$from, $new]) {
                $params     = ['path' => $path, 'from' => $from, 'to' => $new];
                $problems[] = StoreRefused::problem('large-change', $params, $path);
            }
        }
        if ($problems !== []) {
            throw new StoreRefused($problems);
        }
    }

    /**
     * The money or percent values that moved ten times or more, as [from, to] pairs: the value
     * itself, or the fields of a map such as { "value": amount } or { "minimum", "maximum" }.
     *
     * @return list<array{int, int}>
     */
    private static function slips(mixed $current, mixed $value, string $field): array
    {
        if (is_int($current) && is_int($value)) {
            $counted = in_array($field, self::FIELDS, true) && $current > 0 && $value > 0;

            return $counted && self::isSlip($current, $value) ? [[$current, $value]] : [];
        }
        if (is_array($current) === false || is_array($value) === false || array_is_list($value)) {
            return [];
        }
        $slips = [];
        foreach ($value as $key => $inner) {
            $slips = [...$slips, ...self::slips($current[$key] ?? null, $inner, (string) $key)];
        }

        return $slips;
    }

    private static function isSlip(int $current, int $value): bool
    {
        return $value >= $current * self::RATIO || $value * self::RATIO <= $current;
    }

    private static function field(string $path): string
    {
        $parts = explode('.', $path);

        return (string) end($parts);
    }

    /**
     * The profile's value at a path.
     *
     * @param array<string, mixed> $profile
     */
    private static function current(array $profile, string $path): mixed
    {
        $pointer = OverridePaths::find($profile, $path)['pointer'] ?? null;
        if ($pointer === null) {
            return null;
        }
        $cursor = $profile;
        foreach ($pointer as $part) {
            $cursor = is_array($cursor) ? ($cursor[$part] ?? null) : null;
        }

        return $cursor;
    }
}
