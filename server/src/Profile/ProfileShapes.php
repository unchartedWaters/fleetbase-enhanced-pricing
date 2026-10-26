<?php

namespace Fleetbase\EnhancedPricing\Profile;

use Fleetbase\EnhancedPricing\Profile\Schema as S;

/**
 * The shape of each part of a profile: its fields, their types and defaults. Parts whose fields
 * depend on one of their values (an attribute's type, a fee's kind, a service rate's method) are
 * checked in two steps: that value first, then the fields it allows. docs/profile-format.md
 * describes every field.
 */
final class ProfileShapes
{
    public const WEIGHT_UNITS = ['g', 'oz', 'lb', 'kg'];

    public const VOLUME_UNITS = ['cm3', 'm3', 'in3', 'ft3'];

    public const MAX_DISTANCE = '20000';

    public function __construct(private readonly S $schema)
    {
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function topLevel(): array
    {
        return [
            'currency'            => S::type('currency'),
            'timezone'            => S::type('timezone'),
            'distance_unit'       => S::enum(['km', 'mi']),
            'products'            => S::listOf(S::object([
                'code' => S::type('code'),
                'name' => S::type('text'),
            ]), 1, 50),
            'attributes'          => S::optional(S::listOf(S::type('raw'), 0, 20), []),
            'entity_types'        => S::listOf(S::type('raw'), 1, 20),
            'default_entity_type' => S::type('key'),
            'rate_tables'         => S::optional(S::listOf(S::object(self::rateTable()), 0, 10), []),
            'service_rates'       => S::listOf(S::type('raw'), 1, 20),
            'fees'                => S::optional(S::listOf(S::type('raw'), 0, 50), []),
            'subtotal_fees'       => S::optional(S::listOf(S::type('raw'), 0, 50), []),
            'manual_charges'      => S::optional(S::listOf(S::object(self::manualCharge()), 0, 20), []),
            'holiday_lists'       => S::optional([
                'type' => 'map',
                'max'  => 10,
                'of'   => S::listOf(S::type('date'), 0, 400),
            ], []),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function attribute(mixed $raw, string $path): ?array
    {
        $type   = $this->discriminator($raw, 'type', ['boolean', 'choice', 'number'], $path);
        $fields = [
            'key'          => S::type('key'),
            'name'         => S::type('text'),
            'scope'        => S::enum(['order', 'entity']),
            'type'         => S::type('raw'),
            'required'     => S::optional(S::type('boolean'), false),
            'required_for' => S::optional(S::listOf(S::type('key'), 0, 20), []),
        ];
        if ($type === 'choice') {
            $fields['choices'] = S::listOf(S::object(['key' => S::type('key'), 'name' => S::type('text')]), 1, 50);
        }
        if ($type === 'number') {
            $fields['unit']    = S::optional(S::nullable(S::type('text')));
            $fields['maximum'] = S::optional(S::number('0', '1000000', true), '1000000');
        }
        if ($type === null) {
            return null;
        }
        $attribute = $this->schema->fields($raw, $fields, $path);
        if ($attribute !== null && $attribute['required'] === true && $attribute['required_for'] !== []) {
            $this->schema->add(Problem::atPath(S::join($path, 'required_for'), 'required-and-required-for'));
        }

        return $attribute;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function entityType(mixed $raw, string $path): ?array
    {
        return $this->schema->fields($raw, [
            'key'       => S::type('key'),
            'name'      => S::type('text'),
            'own_price' => S::optional(S::nullable(S::object([
                'column'  => S::type('key'),
                'product' => S::type('code'),
            ]))),
        ], $path);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function serviceRate(mixed $raw, string $path): ?array
    {
        $method = $this->discriminator($raw, 'method', ['tiers', 'per_meter', 'fixed_rate'], $path);
        if ($method === null) {
            return null;
        }
        $fields = [
            'key'        => S::type('key'),
            'name'       => S::type('text'),
            'product'    => S::type('code'),
            'rate_table' => S::optional(S::nullable(S::type('key'))),
            'line_name'  => S::type('raw'),
            'method'     => S::type('raw'),
            'minimum'    => S::optional(S::nullable(S::type('money'))),
            'enabled'    => S::optional(S::type('boolean'), true),
            $method      => S::type('raw'),
        ];
        $rate = $this->schema->fields($raw, $fields, $path);
        if ($rate === null) {
            return null;
        }
        $rate['line_name'] = $this->choiceOf(
            $rate['line_name'],
            ['column' => S::type('key'), 'text' => S::type('text')],
            S::join($path, 'line_name'),
        );
        $rate[$method]     = match ($method) {
            'tiers'      => $this->tiers($rate['tiers'], S::join($path, 'tiers')),
            'per_meter'  => $this->schema->value(
                $rate['per_meter'],
                S::object(self::perMeter()),
                S::join($path, 'per_meter'),
            ),
            default      => $this->fixedRate($rate['fixed_rate'], S::join($path, 'fixed_rate')),
        };

        return $rate;
    }

    /**
     * @return list<array<string, mixed>|null>|null
     */
    public function tiers(mixed $raw, string $path): ?array
    {
        $tiers = $this->schema->value(
            $raw,
            S::listOf(S::object(['up_to' => S::nullable(S::type('raw')), 'price' => S::type('raw')]), 1, 10),
            $path,
        );
        if (is_array($tiers) === false) {
            return null;
        }
        $normalized = [];
        foreach ($tiers as $index => $tier) {
            if (is_array($tier) === false) {
                $normalized[] = null;
                continue;
            }
            $tierPath      = S::join($path, $index);
            if ($tier['up_to'] !== null) {
                $tier['up_to'] = $this->choiceOf(
                    $tier['up_to'],
                    ['value' => S::type('integer', ['min' => 1, 'max' => 100000]), 'column' => S::type('key')],
                    S::join($tierPath, 'up_to'),
                );
            }
            $tier['price'] = $this->choiceOf(
                $tier['price'],
                ['value' => S::type('money'), 'column' => S::type('key')],
                S::join($tierPath, 'price'),
            );
            $normalized[]  = ProfileReferences::map($tier);
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fixedRate(mixed $raw, string $path): ?array
    {
        $fixed = $this->schema->fields($raw, ['amount' => S::type('raw')], $path);
        if ($fixed === null) {
            return null;
        }
        $fixed['amount'] = $this->choiceOf(
            $fixed['amount'],
            ['value' => S::type('money'), 'column' => S::type('key')],
            S::join($path, 'amount'),
        );

        return $fixed;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function perMeter(): array
    {
        return [
            'rate'             => S::type('money'),
            'base_fee'         => S::optional(S::type('money'), 0),
            'minimum_distance' => S::optional(S::number(), '0'),
            'maximum_distance' => S::number('0', self::MAX_DISTANCE, true),
            'rounding'         => S::enum(['up', 'nearest', 'none']),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fee(mixed $raw, string $path): ?array
    {
        $kind = $this->discriminator($raw, 'kind', ['peak_hours', 'parcel', 'additional'], $path);
        if ($kind === null) {
            return null;
        }

        return $this->schema->fields($raw, self::feeFields($kind), $path);
    }

    /**
     * The fields of a fee of one kind.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function feeFields(string $kind): array
    {
        $fields = [
            'key'             => S::type('key'),
            'kind'            => S::type('raw'),
            'name'            => S::type('text'),
            'product'         => S::type('code'),
            'applies_to'      => self::codes(1),
            'charge'          => S::type('key'),
            'minimum'         => S::optional(S::nullable(S::type('money'))),
            'maximum'         => S::optional(S::nullable(S::type('money'))),
            'effective_from'  => S::optional(S::nullable(S::type('date'))),
            'effective_until' => S::optional(S::nullable(S::type('date'))),
            'enabled'         => S::optional(S::type('boolean'), true),
        ];
        if ($kind !== 'parcel') {
            $fields['amount']     = S::nullable(S::type('money'));
            $fields['if_missing'] = S::optional(S::enum(['stop', 'skip']), 'stop');
        }

        return $fields + match ($kind) {
            'peak_hours' => [
                'window'   => S::optional(S::nullable(S::object(['from' => S::type('time'), 'to' => S::type('time')]))),
                'weekdays' => S::optional(S::nullable(S::listOf(S::type('integer', ['min' => 1, 'max' => 7]), 1, 7))),
                'holidays' => S::optional(S::nullable(S::type('key'))),
            ],
            'parcel' => [
                'basis'      => S::enum(['weight', 'volume']),
                'measure'    => S::optional(S::nullable(S::enum(['actual', 'dimensional', 'greater']))),
                'divisor'    => S::optional(S::nullable(S::number('0', '1000000', true))),
                'unit'       => S::type('key'),
                'scope'      => S::enum(['entity', 'order']),
                'thresholds' => self::thresholds(),
            ],
            default => [
                'attribute' => S::type('key'),
                'equals'    => S::optional(S::nullable(S::type('key'))),
            ],
        };
    }

    /**
     * @return array<string, mixed>
     */
    public static function thresholds(): array
    {
        return S::listOf(S::object(['over' => S::number(), 'amount' => S::type('money')]), 1, 10);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function subtotalFee(mixed $raw, string $path): ?array
    {
        return $this->schema->fields($raw, self::subtotalFeeFields(), $path);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function subtotalFeeFields(): array
    {
        return [
            'key'             => S::type('key'),
            'name'            => S::type('text'),
            'product'         => S::type('code'),
            'applies_to'      => self::codes(1),
            'requires'        => S::optional(self::codes(0), []),
            'skipped_for'     => S::optional(self::codes(0), []),
            'mode'            => S::enum(['percentage', 'flat']),
            'percentage'      => S::optional(S::nullable(S::type('percent'))),
            'amount'          => S::optional(S::nullable(S::type('money'))),
            'charge'          => S::enum(['per_order', 'per_line', 'per_entity']),
            'if_missing'      => S::optional(S::enum(['stop', 'skip']), 'stop'),
            'minimum'         => S::optional(S::nullable(S::type('money'))),
            'maximum'         => S::optional(S::nullable(S::type('money'))),
            'effective_from'  => S::optional(S::nullable(S::type('date'))),
            'effective_until' => S::optional(S::nullable(S::type('date'))),
            'enabled'         => S::optional(S::type('boolean'), true),
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function manualCharge(): array
    {
        return [
            'key'             => S::type('key'),
            'name'            => S::type('text'),
            'product'         => S::type('code'),
            'amount'          => S::optional(S::nullable(S::type('money'))),
            'entered'         => S::optional(S::nullable(S::object([
                'minimum' => S::type('money'),
                'maximum' => S::type('money'),
            ]))),
            'quantity'        => S::optional(S::nullable(S::object([
                'unit'    => S::type('text'),
                'step'    => S::number('0', '1000000', true),
                'maximum' => S::number('0', '1000000', true),
            ]))),
            'offered'         => S::optional(S::type('boolean'), true),
            'effective_from'  => S::optional(S::nullable(S::type('date'))),
            'effective_until' => S::optional(S::nullable(S::type('date'))),
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function rateTable(): array
    {
        return [
            'key'     => S::type('key'),
            'columns' => S::listOf(S::object([
                'key'      => S::type('key'),
                'type'     => S::enum(['text', 'money', 'integer', 'number']),
                'required' => S::optional(S::type('boolean'), false),
            ]), 1, 30),
            'row_key' => S::listOf(S::type('key'), 1, 5),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function codes(int $min): array
    {
        return S::listOf(S::type('code'), $min, 50);
    }

    /**
     * An object holding exactly one of the given fields, such as { "value": 2 } or
     * { "column": "first" }.
     *
     * @param array<string, array<string, mixed>> $choices
     *
     * @return array<string, mixed>|null
     */
    public function choiceOf(mixed $raw, array $choices, string $path): ?array
    {
        if (is_array($raw) && count(array_intersect_key($raw, $choices)) !== 1) {
            $this->schema->add(Problem::atPath(
                $path,
                'needs-exactly-one',
                ['fields' => implode(', ', array_keys($choices))],
            ));

            return null;
        }
        $optional = array_map(static fn (array $spec): array => S::optional($spec), $choices);
        $value    = $this->schema->fields($raw, $optional, $path);

        return $value === null ? null : array_filter($value, static fn (mixed $item): bool => $item !== null);
    }

    /**
     * Reads the field that decides an object's other fields, reporting it when it is missing or
     * not one of the choices.
     *
     * @param list<string> $choices
     */
    private function discriminator(mixed $raw, string $field, array $choices, string $path): ?string
    {
        if (is_array($raw) === false || ($raw !== [] && array_is_list($raw))) {
            $this->schema->add(Problem::atPath($path, 'wrong-type', ['expected' => 'object']));

            return null;
        }
        if (array_key_exists($field, $raw) === false) {
            $this->schema->add(Problem::atPath(S::join($path, $field), 'missing-field', ['field' => $field]));

            return null;
        }
        $value = $this->schema->value($raw[$field], S::enum($choices), S::join($path, $field));

        return is_string($value) ? $value : null;
    }
}
