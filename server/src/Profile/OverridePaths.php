<?php

namespace Fleetbase\EnhancedPricing\Profile;

use Fleetbase\EnhancedPricing\Profile\ProfileReferences as R;
use Fleetbase\EnhancedPricing\Profile\Schema as S;

/**
 * The values a customer override or a dated change can name, by path: the profile's section, the
 * rule's key and a field, such as "fees.heavy.thresholds". Each path knows where its value sits in a normalized
 * profile, how to check a new value, and whether the value can also change by date.
 */
final class OverridePaths
{
    public const ALLOWED_SERVICE_RATES = 'allowed_service_rates';

    private const FEE_FIELDS = [
        'common'     => ['applies_to', 'minimum', 'maximum', 'enabled'],
        'peak_hours' => ['amount', 'if_missing', 'window', 'weekdays', 'holidays'],
        'parcel'     => ['thresholds', 'divisor'],
        'additional' => ['amount', 'if_missing', 'equals'],
    ];

    private const SUBTOTAL_FIELDS = [
        'applies_to',
        'requires',
        'skipped_for',
        'percentage',
        'amount',
        'if_missing',
        'minimum',
        'maximum',
        'enabled',
    ];

    private const MANUAL_FIELDS = ['amount', 'entered', 'quantity', 'offered'];

    /** Fields whose values are money, percents or numbers, which may change by date. */
    private const DATED = [
        'amount',
        'percentage',
        'minimum',
        'maximum',
        'thresholds',
        'tiers',
        'rate',
        'base_fee',
        'minimum_distance',
    ];

    /**
     * Finds a path in a normalized profile, or null when the profile has no such value or it
     * can't be overridden.
     *
     * @param array<string, mixed> $profile
     *
     * @return array{pointer: list<string|int>|null, dated: bool, check: \Closure(S, mixed, string): mixed}|null
     */
    public static function find(array $profile, string $path): ?array
    {
        if ($path === self::ALLOWED_SERVICE_RATES) {
            return self::entry(null, false, S::listOf(S::type('key'), 0, 20));
        }
        $parts = explode('.', $path);
        if (
            count($parts) === 2
            && $parts[0] === 'holiday_lists'
            && array_key_exists($parts[1], R::map($profile['holiday_lists'] ?? []))
        ) {
            return self::entry(['holiday_lists', $parts[1]], false, S::listOf(S::type('date'), 0, 400));
        }
        if (count($parts) < 3) {
            return null;
        }
        [$section, $key] = $parts;
        $field           = implode('.', array_slice($parts, 2));
        $index           = self::indexOf($profile, $section, $key);
        if ($index === null) {
            return null;
        }
        $rule = R::items($profile[$section])[$index];

        return match ($section) {
            'service_rates'  => self::serviceRate($rule, $index, $field),
            'fees'           => self::fee($rule, $index, $field),
            'subtotal_fees'  => in_array($field, self::SUBTOTAL_FIELDS, true)
                ? self::entry(
                    ['subtotal_fees', $index, $field],
                    in_array($field, self::DATED, true),
                    self::bare(ProfileShapes::subtotalFeeFields()[$field]),
                )
                : null,
            'manual_charges' => in_array($field, self::MANUAL_FIELDS, true)
                ? self::manualCharge($rule, $index, $field)
                : null,
            default => null,
        };
    }

    /**
     * The problem with a path that find() doesn't know: the rule it names doesn't exist, or the
     * value can't be overridden.
     *
     * @param array<string, mixed> $profile a normalized profile
     */
    public static function unknown(array $profile, string $path, string $problemPath): Problem
    {
        $parts = explode('.', $path);
        $kinds = [
            'service_rates'  => 'service_rate',
            'fees'           => 'fee',
            'subtotal_fees'  => 'subtotal_fee',
            'manual_charges' => 'manual_charge',
        ];
        $kind = count($parts) >= 3 ? ($kinds[$parts[0]] ?? null) : null;
        if ($kind !== null && self::indexOf($profile, $parts[0], $parts[1]) === null) {
            return Problem::atPath($problemPath, 'unknown-reference', ['kind' => $kind, 'key' => $parts[1]]);
        }

        return Problem::atPath($problemPath, 'unknown-override', ['path' => $path]);
    }

    /**
     * A path into a normalized profile with each rule's position replaced by its key, for
     * example "fees.1.minimum" as "fees.heavy.minimum", the way overrides and dated changes
     * name it. A path that doesn't start at a rule is returned as it is.
     *
     * @param array<string, mixed> $profile
     */
    public static function keyed(array $profile, string $path): string
    {
        $parts = explode('.', $path);
        if (count($parts) < 2 || ctype_digit($parts[1]) === false) {
            return $path;
        }
        $rule = R::items($profile[$parts[0]] ?? [])[(int) $parts[1]] ?? null;
        $key  = $rule['key'] ?? $rule['code'] ?? null;
        if (is_string($key) === false) {
            return $path;
        }
        $parts[1] = $key;

        return implode('.', $parts);
    }

    /**
     * Sets a value at a pointer in a profile.
     *
     * @param array<string, mixed> $profile
     * @param list<string|int>     $pointer
     *
     * @return array<string, mixed>
     */
    public static function apply(array $profile, array $pointer, mixed $value): array
    {
        $cursor = &$profile;
        foreach ($pointer as $part) {
            if (is_array($cursor) === false) {
                return R::map($profile);
            }
            $cursor = &$cursor[$part];
        }
        $cursor = $value;

        return R::map($profile);
    }

    /**
     * @param array<string, mixed> $rule
     *
     * @return array{pointer: list<string|int>|null, dated: bool, check: \Closure(S, mixed, string): mixed}|null
     */
    private static function serviceRate(array $rule, int $index, string $field): ?array
    {
        $pointer = ['service_rates', $index];
        $method  = $rule['method'];

        return match (true) {
            $field === 'enabled'                      => self::entry(
                [...$pointer, 'enabled'],
                false,
                S::type('boolean'),
            ),
            $field === 'minimum'                      => self::entry(
                [...$pointer, 'minimum'],
                true,
                S::nullable(S::type('money')),
            ),
            $field === 'tiers' && $method === 'tiers' => [
                'pointer' => [...$pointer, 'tiers'],
                'dated'   => true,
                'check'   => static fn (
                    S $schema,
                    mixed $value,
                    string $path,
                ): mixed => (new ProfileShapes($schema))->tiers($value, $path),
            ],
            $field === 'fixed_rate.amount' && $method === 'fixed_rate' => [
                'pointer' => [...$pointer, 'fixed_rate', 'amount'],
                'dated'   => true,
                'check'   => static fn (
                    S $schema,
                    mixed $value,
                    string $path,
                ): mixed => (new ProfileShapes($schema))->choiceOf(
                    $value,
                    ['value' => S::type('money'), 'column' => S::type('key')],
                    $path,
                ),
            ],
            str_starts_with($field, 'per_meter.') && $method === 'per_meter' => self::perMeter(
                $pointer,
                substr($field, 10),
            ),
            default                                                          => null,
        };
    }

    /**
     * @param list<string|int> $pointer
     *
     * @return array{pointer: list<string|int>|null, dated: bool, check: \Closure(S, mixed, string): mixed}|null
     */
    private static function perMeter(array $pointer, string $field): ?array
    {
        // The maximum distance is a safety limit, so it stays the profile's.
        if (in_array($field, ['rate', 'base_fee', 'minimum_distance', 'rounding'], true) === false) {
            return null;
        }

        return self::entry(
            [...$pointer, 'per_meter', $field],
            in_array($field, self::DATED, true),
            self::bare(ProfileShapes::perMeter()[$field]),
        );
    }

    /**
     * @param array<string, mixed> $rule
     *
     * @return array{pointer: list<string|int>|null, dated: bool, check: \Closure(S, mixed, string): mixed}|null
     */
    private static function fee(array $rule, int $index, string $field): ?array
    {
        $kind    = R::text($rule['kind']);
        $allowed = array_merge(self::FEE_FIELDS['common'], self::FEE_FIELDS[$kind] ?? []);
        if (in_array($field, $allowed, true) === false) {
            return null;
        }

        return self::entry(
            ['fees', $index, $field],
            in_array($field, self::DATED, true),
            self::bare(ProfileShapes::feeFields($kind)[$field]),
        );
    }

    /**
     * A manual charge's value. Whether its amount, entered limits and quantity are set decides
     * how the charge works, so an override or change may replace them but not add or remove one.
     *
     * @param array<string, mixed> $rule
     *
     * @return array{pointer: list<string|int>|null, dated: bool, check: \Closure(S, mixed, string): mixed}
     */
    private static function manualCharge(array $rule, int $index, string $field): array
    {
        $entry = self::entry(
            ['manual_charges', $index, $field],
            $field === 'amount',
            self::bare(ProfileShapes::manualCharge()[$field]),
        );
        if ($field === 'offered') {
            return $entry;
        }
        $check          = $entry['check'];
        $set            = ($rule[$field] ?? null) !== null;
        $entry['check'] = static function (S $schema, mixed $value, string $path) use ($check, $set, $field): mixed {
            if (($value !== null) !== $set) {
                $schema->add(Problem::atPath($path, 'changes-charge-mode', ['field' => $field]));

                return null;
            }

            return $check($schema, $value, $path);
        };

        return $entry;
    }

    /**
     * @param list<string|int>|null $pointer
     * @param array<string, mixed>  $spec
     *
     * @return array{pointer: list<string|int>|null, dated: bool, check: \Closure(S, mixed, string): mixed}
     */
    private static function entry(?array $pointer, bool $dated, array $spec): array
    {
        return [
            'pointer' => $pointer,
            'dated'   => $dated,
            'check'   => static fn (S $schema, mixed $value, string $path): mixed => $schema->value(
                $value,
                $spec,
                $path,
            ),
        ];
    }

    /**
     * A field's description without its default: an override always gives the value.
     *
     * @param array<string, mixed> $spec
     *
     * @return array<string, mixed>
     */
    private static function bare(array $spec): array
    {
        unset($spec['optional'], $spec['default']);

        return $spec;
    }

    /**
     * @param array<string, mixed> $profile
     */
    private static function indexOf(array $profile, string $section, string $key): ?int
    {
        if (in_array($section, ['service_rates', 'fees', 'subtotal_fees', 'manual_charges'], true) === false) {
            return null;
        }
        foreach (R::items($profile[$section] ?? []) as $index => $rule) {
            if ($rule['key'] === $key) {
                return $index;
            }
        }

        return null;
    }
}
