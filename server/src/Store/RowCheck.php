<?php

namespace Fleetbase\EnhancedPricing\Store;

use Fleetbase\EnhancedPricing\Profile\Problem;
use Fleetbase\EnhancedPricing\Profile\ProfileReferences as R;
use Fleetbase\EnhancedPricing\Profile\Schema;
use Fleetbase\EnhancedPricing\Support\Exact;

/**
 * Checks a rate card row against its rate table, by the same rules the engine applies when an
 * order uses the row (ServiceFee::checkRow), so a row that saves is a row that prices. Also makes
 * the row's key: the key columns' values in the table's row_key order, as a JSON array, hashed.
 */
final class RowCheck
{
    private const KEY_JSON = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;

    /**
     * @param array<string, mixed> $profile a normalized profile
     *
     * @return array<string, mixed>|null
     */
    public static function table(array $profile, string $key): ?array
    {
        foreach (R::items($profile['rate_tables'] ?? []) as $table) {
            if ($table['key'] === $key) {
                return $table;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $table
     * @param array<mixed>         $values column key => value
     *
     * @return list<Problem>
     */
    public static function check(array $table, array $values, string $path): array
    {
        $columns = self::columns($table);
        $keys    = R::strings($table['row_key']);
        $found   = [];
        foreach ($values as $key => $value) {
            $column = $columns[(string) $key] ?? null;
            if ($column === null) {
                $found[] = StoreRefused::problem('row-unknown-column', ['column' => (string) $key], "$path.$key");
            } elseif (self::blank($value) === false && self::valueFits($column, $value) === false) {
                $found[] = StoreRefused::problem(
                    'row-wrong-type',
                    ['column' => (string) $key, 'type' => R::text($column['type'])],
                    "$path.$key",
                );
            }
        }
        foreach ($columns as $key => $column) {
            $required = $column['required'] === true || in_array($key, $keys, true);
            if ($required && self::blank($values[$key] ?? null)) {
                $found[] = StoreRefused::problem('row-missing-value', ['column' => $key], "$path.$key");
            }
        }

        return $found;
    }

    /**
     * The engine's ServiceFee::valueFits, for a column of the table.
     *
     * @param array<string, mixed> $column
     */
    public static function valueFits(array $column, mixed $value): bool
    {
        return match ($column['type']) {
            'text'    => is_string($value) && mb_strlen($value) <= Schema::MAX_TEXT,
            'money'   => is_int($value) && $value >= 0 && $value <= Schema::MAX_MONEY,
            'integer' => is_int($value) && $value >= 0 && $value <= 100000,
            default   => ($number = Exact::canonical($value)) !== null && str_starts_with($number, '-') === false,
        };
    }

    /**
     * The key columns' values, in row_key order. Numbers are in their canonical form, so 1.50 and
     * "1.5" are the same key.
     *
     * @param array<string, mixed> $table
     * @param array<mixed>         $values
     *
     * @return list<mixed>
     */
    public static function keyValues(array $table, array $values): array
    {
        $columns = self::columns($table);
        $key     = [];
        foreach (R::strings($table['row_key']) as $column) {
            $value = $values[$column] ?? null;
            $key[] = ($columns[$column]['type'] ?? null) === 'number' ? Exact::canonical($value) : $value;
        }

        return $key;
    }

    /**
     * @param list<mixed> $keyValues
     */
    public static function hash(array $keyValues): string
    {
        return hash('sha256', json_encode($keyValues, self::KEY_JSON));
    }

    /**
     * @param array<string, mixed> $table
     *
     * @return array<string, array<string, mixed>>
     */
    private static function columns(array $table): array
    {
        $columns = [];
        foreach (R::items($table['columns']) as $column) {
            $columns[R::text($column['key'])] = $column;
        }

        return $columns;
    }

    private static function blank(mixed $value): bool
    {
        return $value === null || $value === '';
    }
}
