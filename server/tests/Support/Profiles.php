<?php

namespace Fleetbase\EnhancedPricing\Tests\Support;

/**
 * The example profile from server/tests/Fixtures and small edits to it, so each test changes
 * only the value it is about.
 */
final class Profiles
{
    /**
     * @return array<string, mixed>
     */
    public static function example(): array
    {
        $json    = file_get_contents(__DIR__ . '/../Fixtures/example-profile.json');
        $decoded = json_decode((string) $json, true, 64, JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Sets a value by dot path, for example "fees.1.thresholds.0.over".
     *
     * @param array<string, mixed> $profile
     *
     * @return array<string, mixed>
     */
    public static function set(array $profile, string $path, mixed $value): array
    {
        $keys   = explode('.', $path);
        $cursor = &$profile;
        foreach ($keys as $key) {
            if (is_array($cursor) === false) {
                $cursor = [];
            }
            $cursor = &$cursor[ctype_digit($key) ? (int) $key : $key];
        }
        $cursor = $value;

        return $profile;
    }

    /**
     * Removes a value by dot path. List positions after it move down by one.
     *
     * @param array<string, mixed> $profile
     *
     * @return array<string, mixed>
     */
    public static function remove(array $profile, string $path): array
    {
        $keys   = explode('.', $path);
        $last   = array_pop($keys);
        $cursor = &$profile;
        foreach ($keys as $key) {
            $cursor = &$cursor[ctype_digit($key) ? (int) $key : $key];
        }
        if (is_array($cursor)) {
            $isList = array_is_list($cursor);
            unset($cursor[ctype_digit($last) ? (int) $last : $last]);
            if ($isList) {
                $cursor = array_values($cursor);
            }
        }

        return $profile;
    }

    /**
     * Appends an item to a list by dot path.
     *
     * @param array<string, mixed> $profile
     *
     * @return array<string, mixed>
     */
    public static function append(array $profile, string $path, mixed $item): array
    {
        $keys   = explode('.', $path);
        $cursor = &$profile;
        foreach ($keys as $key) {
            $cursor = &$cursor[ctype_digit($key) ? (int) $key : $key];
        }
        if (is_array($cursor) === false) {
            $cursor = [];
        }
        $cursor[] = $item;

        return $profile;
    }
}
