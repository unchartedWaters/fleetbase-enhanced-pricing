<?php

namespace Fleetbase\EnhancedPricing\Profile;

use Fleetbase\EnhancedPricing\Support\Exact;

/**
 * Checks a value against a small field description and returns it normalized: defaults filled
 * in, numbers as canonical decimal strings. Every problem is collected rather than stopping at
 * the first, so a person fixing a profile sees all of them at once.
 *
 * A description is an array with a "type" and, depending on it, "nullable", "optional" with a
 * "default", "min", "max", "choices", "of" (list and map items) or "fields" (objects). The static
 * builders below make them.
 */
final class Schema
{
    public const MAX_MONEY = 100000000;

    public const MAX_PERCENT = 100000;

    public const MAX_TEXT = 255;

    private const KEY = '/^[a-z0-9][a-z0-9_-]{0,63}$/';

    private const CODE = '/^[A-Za-z0-9][A-Za-z0-9_.-]{0,63}$/';

    private const TIME = '/^([01]\d|2[0-3]):[0-5]\d$/';

    /** @var list<Problem> */
    private array $problems = [];

    /** @var array<string, true>|null */
    private static ?array $timezones = null;

    /**
     * @return list<Problem>
     */
    public function problems(): array
    {
        return $this->problems;
    }

    public function add(Problem $problem): void
    {
        $this->problems[] = $problem;
    }

    /**
     * @param array<string, mixed> $spec
     */
    public function value(mixed $value, array $spec, string $path): mixed
    {
        if ($value === null) {
            if (($spec['nullable'] ?? false) === true) {
                return null;
            }
            $this->add(Problem::atPath($path, 'wrong-type', ['expected' => self::string($spec['type'] ?? '')]));

            return null;
        }

        return match ($spec['type'] ?? null) {
            'money'    => $this->integerIn($value, 0, self::MAX_MONEY, $path, 'money'),
            'percent'  => $this->integerIn($value, 0, self::MAX_PERCENT, $path, 'percent'),
            'integer'  => $this->integerIn(
                $value,
                self::int($spec['min'] ?? 0),
                self::int($spec['max'] ?? PHP_INT_MAX),
                $path,
                'integer',
            ),
            'number'   => $this->checkNumber($value, $spec, $path),
            'boolean'  => $this->boolean($value, $path),
            'text'     => $this->text($value, $path),
            'key'      => $this->pattern($value, self::KEY, $path, 'key'),
            'code'     => $this->pattern($value, self::CODE, $path, 'code'),
            'time'     => $this->pattern($value, self::TIME, $path, 'time'),
            'date'     => $this->date($value, $path),
            'timezone' => $this->timezone($value, $path),
            'currency' => $this->pattern($value, '/^[A-Z]{3}$/', $path, 'currency'),
            'enum'     => $this->choice($value, $spec, $path),
            'list'     => $this->checkList($value, $spec, $path),
            'map'      => $this->checkMap($value, $spec, $path),
            'object'   => $this->checkObject($value, $spec, $path),
            'raw'      => $value,
            default    => throw new \LogicException('Unknown field type at ' . $path),
        };
    }

    /**
     * Checks an object against its fields: unknown fields are refused, missing optional fields
     * take their default, and missing required fields are reported.
     *
     * @param array<string, array<string, mixed>> $fields
     *
     * @return array<string, mixed>|null
     */
    public function fields(mixed $value, array $fields, string $path): ?array
    {
        if (is_array($value) === false || ($value !== [] && array_is_list($value))) {
            $this->add(Problem::atPath($path, 'wrong-type', ['expected' => 'object']));

            return null;
        }

        $normalized = [];
        foreach (array_keys($value) as $name) {
            if (array_key_exists((string) $name, $fields) === false) {
                $this->add(Problem::atPath(
                    self::join($path, (string) $name),
                    'unknown-field',
                    ['field' => (string) $name],
                ));
            }
        }
        foreach ($fields as $name => $spec) {
            $fieldPath = self::join($path, $name);
            if (array_key_exists($name, $value) === false) {
                if (($spec['optional'] ?? false) === true) {
                    $normalized[$name] = $spec['default'] ?? null;
                    continue;
                }
                $this->add(Problem::atPath($fieldPath, 'missing-field', ['field' => $name]));
                $normalized[$name] = null;
                continue;
            }
            $normalized[$name] = $this->value($value[$name], $spec, $fieldPath);
        }

        return $normalized;
    }

    public static function join(string $path, string|int $part): string
    {
        return $path === '' ? (string) $part : $path . '.' . $part;
    }

    // Builders.

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public static function type(string $type, array $options = []): array
    {
        return ['type' => $type] + $options;
    }

    /**
     * @param array<string, mixed> $spec
     *
     * @return array<string, mixed>
     */
    public static function optional(array $spec, mixed $default = null): array
    {
        return ['optional' => true, 'default' => $default] + $spec;
    }

    /**
     * @param array<string, mixed> $spec
     *
     * @return array<string, mixed>
     */
    public static function nullable(array $spec): array
    {
        return ['nullable' => true] + $spec;
    }

    /**
     * @param array<string, mixed> $item
     *
     * @return array<string, mixed>
     */
    public static function listOf(array $item, int $min = 0, int $max = 50): array
    {
        return ['type' => 'list', 'of' => $item, 'min' => $min, 'max' => $max];
    }

    /**
     * @param array<string, array<string, mixed>> $fields
     *
     * @return array<string, mixed>
     */
    public static function object(array $fields): array
    {
        return ['type' => 'object', 'fields' => $fields];
    }

    /**
     * @param list<string> $choices
     *
     * @return array<string, mixed>
     */
    public static function enum(array $choices): array
    {
        return ['type' => 'enum', 'choices' => $choices];
    }

    /**
     * A number: up to 7 digits and 6 decimals, from min (inclusive, or exclusive with "above").
     *
     * @return array<string, mixed>
     */
    public static function number(string $min = '0', ?string $max = null, bool $above = false): array
    {
        return ['type' => 'number', 'min' => $min, 'max' => $max, 'above' => $above];
    }

    // Checks.

    private function integerIn(mixed $value, int $min, int $max, string $path, string $expected): ?int
    {
        if (is_int($value) === false) {
            $this->add(Problem::atPath($path, 'wrong-type', ['expected' => $expected]));

            return null;
        }
        if ($value < $min || $value > $max) {
            $this->add(Problem::atPath($path, 'out-of-range', ['min' => $min, 'max' => $max]));

            return null;
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $spec
     */
    private function checkNumber(mixed $value, array $spec, string $path): ?string
    {
        $canonical = Exact::canonical($value);
        if ($canonical === null) {
            $this->add(Problem::atPath($path, 'wrong-type', ['expected' => 'number']));

            return null;
        }
        $number = Exact::rational($canonical);
        $min    = self::string($spec['min'] ?? '0');
        $max    = isset($spec['max']) ? self::string($spec['max']) : null;
        $below  = ($spec['above'] ?? false) === true ? $number->isLessThanOrEqualTo($min) : $number->isLessThan($min);
        if ($below || ($max !== null && $number->isGreaterThan($max))) {
            $this->add(Problem::atPath($path, 'out-of-range', ['min' => $min, 'max' => $max]));

            return null;
        }

        return $canonical;
    }

    private function boolean(mixed $value, string $path): ?bool
    {
        if (is_bool($value) === false) {
            $this->add(Problem::atPath($path, 'wrong-type', ['expected' => 'boolean']));

            return null;
        }

        return $value;
    }

    private function text(mixed $value, string $path): ?string
    {
        if (is_string($value) === false) {
            $this->add(Problem::atPath($path, 'wrong-type', ['expected' => 'text']));

            return null;
        }
        $length = mb_strlen($value);
        if ($length === 0) {
            $this->add(Problem::atPath($path, 'too-short', ['min' => 1]));

            return null;
        }
        if ($length > self::MAX_TEXT) {
            $this->add(Problem::atPath($path, 'too-long', ['max' => self::MAX_TEXT]));

            return null;
        }

        return $value;
    }

    private function pattern(mixed $value, string $pattern, string $path, string $expected): ?string
    {
        if (is_string($value) === false || preg_match($pattern, $value) !== 1) {
            $this->add(Problem::atPath($path, 'wrong-type', ['expected' => $expected]));

            return null;
        }

        return $value;
    }

    private function date(mixed $value, string $path): ?string
    {
        if (
            is_string($value)
            && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts) === 1
            && checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])
        ) {
            return $value;
        }
        $this->add(Problem::atPath($path, 'wrong-type', ['expected' => 'date']));

        return null;
    }

    private function timezone(mixed $value, string $path): ?string
    {
        if (self::$timezones === null) {
            self::$timezones = array_fill_keys(\DateTimeZone::listIdentifiers(), true);
        }
        if (is_string($value) && isset(self::$timezones[$value])) {
            return $value;
        }
        $this->add(Problem::atPath($path, 'wrong-type', ['expected' => 'timezone']));

        return null;
    }

    /**
     * @param array<string, mixed> $spec
     */
    private function choice(mixed $value, array $spec, string $path): ?string
    {
        $choices = is_array($spec['choices'] ?? null) ? $spec['choices'] : [];
        if (is_string($value) && in_array($value, $choices, true)) {
            return $value;
        }
        $this->add(Problem::atPath(
            $path,
            'not-one-of',
            ['choices' => implode(', ', array_map(self::string(...), $choices))],
        ));

        return null;
    }

    /**
     * @param array<string, mixed> $spec
     *
     * @return list<mixed>|null
     */
    private function checkList(mixed $value, array $spec, string $path): ?array
    {
        if (is_array($value) === false || array_is_list($value) === false) {
            $this->add(Problem::atPath($path, 'wrong-type', ['expected' => 'list']));

            return null;
        }
        $min = self::int($spec['min'] ?? 0);
        $max = self::int($spec['max'] ?? 50);
        if (count($value) < $min) {
            $this->add(Problem::atPath($path, 'too-few', ['min' => $min]));
        }
        if (count($value) > $max) {
            // Items past the limit are not checked, so an oversized list costs nothing more.
            $this->add(Problem::atPath($path, 'too-many', ['max' => $max]));

            return null;
        }
        /** @var array<string, mixed> $itemSpec */
        $itemSpec = is_array($spec['of'] ?? null) ? $spec['of'] : [];
        $items    = [];
        foreach ($value as $index => $item) {
            $items[] = $this->value($item, $itemSpec, self::join($path, $index));
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $spec
     *
     * @return array<string, mixed>|null
     */
    private function checkMap(mixed $value, array $spec, string $path): ?array
    {
        if (is_array($value) === false || ($value !== [] && array_is_list($value))) {
            $this->add(Problem::atPath($path, 'wrong-type', ['expected' => 'object']));

            return null;
        }
        $max = self::int($spec['max'] ?? 10);
        if (count($value) > $max) {
            $this->add(Problem::atPath($path, 'too-many', ['max' => $max]));

            return null;
        }
        /** @var array<string, mixed> $itemSpec */
        $itemSpec = is_array($spec['of'] ?? null) ? $spec['of'] : [];
        $items    = [];
        foreach ($value as $name => $item) {
            $itemPath = self::join($path, (string) $name);
            if (preg_match(self::KEY, (string) $name) !== 1) {
                $this->add(Problem::atPath($itemPath, 'wrong-type', ['expected' => 'key']));
                continue;
            }
            $items[(string) $name] = $this->value($item, $itemSpec, $itemPath);
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $spec
     *
     * @return array<string, mixed>|null
     */
    private function checkObject(mixed $value, array $spec, string $path): ?array
    {
        /** @var array<string, array<string, mixed>> $fields */
        $fields = is_array($spec['fields'] ?? null) ? $spec['fields'] : [];

        return $this->fields($value, $fields, $path);
    }

    private static function int(mixed $value): int
    {
        return is_int($value) ? $value : 0;
    }

    private static function string(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
