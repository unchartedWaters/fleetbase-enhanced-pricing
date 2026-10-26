<?php

namespace Fleetbase\EnhancedPricing\Store;

/**
 * A value as JSON with object keys sorted, so a value sent again with its keys in another order,
 * or read back from a MySQL JSON column, which sorts them, is the same value. Lists keep their order.
 */
final class Canonical
{
    public static function json(mixed $value): string
    {
        return (string) json_encode(self::sorted($value));
    }

    private static function sorted(mixed $value): mixed
    {
        if (is_array($value) === false) {
            return $value;
        }
        $value = array_map(self::sorted(...), $value);
        if (array_is_list($value) === false) {
            ksort($value);
        }

        return $value;
    }
}
