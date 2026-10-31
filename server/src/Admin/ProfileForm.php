<?php

namespace Fleetbase\EnhancedPricing\Admin;

use Fleetbase\EnhancedPricing\Profile\ProfileShapes;

/**
 * What the console's profile editor draws: every element of a profile, its fields, their types,
 * defaults and choices, so the console never hard-codes the profile format. The profile checks
 * stay in ProfileValidator; this only describes the form, and tests check the two agree.
 *
 * A field is { name, type, optional?, default?, nullable?, options?, ref?, of?, choices? }:
 * - type: key, code, text, money, percent, number, integer, boolean, enum, date, time, timezone,
 *   currency, ref (one key chosen from the profile), refs (a list of them), dates, weekdays,
 *   choice (an object holding exactly one of `choices`), object (`of` a kind) or list (`of` a
 *   kind);
 * - ref: products, entity_types, rate_tables, columns, text_columns, money_columns,
 *   integer_columns, attributes, choices, holiday_lists or service_rates;
 * - fixed: set when the profile is made and never changed by a new version;
 * - when: { field: [values] }, the field is used only while those sibling fields hold one of
 *   the values, and is left out otherwise.
 */
final class ProfileForm
{
    /**
     * @return array{sections: list<array<string, mixed>>, kinds: array<string, array<string, mixed>>}
     */
    public static function spec(): array
    {
        return [
            'sections' => [
                ['key' => 'general', 'kind' => 'profile'],
                ['key' => 'products', 'kind' => 'product', 'list' => true, 'max' => 50],
                ['key' => 'attributes', 'kind' => 'attribute', 'list' => true, 'max' => 20],
                ['key' => 'entity_types', 'kind' => 'entity_type', 'list' => true, 'max' => 20],
                ['key' => 'rate_tables', 'kind' => 'rate_table', 'list' => true, 'max' => 10],
                ['key' => 'service_rates', 'kind' => 'service_rate', 'list' => true, 'max' => 20],
                ['key' => 'fees', 'kind' => 'fee', 'list' => true, 'max' => 50, 'ordered' => true],
                ['key' => 'subtotal_fees', 'kind' => 'subtotal_fee', 'list' => true, 'max' => 50, 'ordered' => true],
                ['key' => 'manual_charges', 'kind' => 'manual_charge', 'list' => true, 'max' => 20],
                ['key' => 'holiday_lists', 'kind' => 'holiday_list', 'map' => true, 'max' => 10],
            ],
            'kinds'    => self::kinds(),
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function kinds(): array
    {
        return [
            'profile'       => ['fields' => [
                self::field('currency', 'currency', ['fixed' => true]),
                self::field('scheduling_timezone', 'timezone'),
                self::enum('distance_unit', ['km', 'mi'], ['fixed' => true]),
                self::field('default_entity_type', 'ref', ['ref' => 'entity_types']),
            ]],
            'product'       => ['fields' => [self::field('code', 'code'), self::field('name', 'text')]],
            'attribute'     => self::attribute(),
            'choice'        => ['fields' => [self::field('key', 'key'), self::field('name', 'text')]],
            'entity_type'   => ['fields' => [
                self::field('key', 'key'),
                self::field('name', 'text'),
                self::optional(self::field('own_price', 'object', ['of' => 'own_price', 'nullable' => true]), null),
            ]],
            'own_price'     => ['fields' => [
                self::field('column', 'ref', ['ref' => 'money_columns']),
                self::field('product', 'ref', ['ref' => 'products']),
            ]],
            'rate_table'    => ['fields' => [
                self::field('key', 'key'),
                self::field('columns', 'list', ['of' => 'column', 'min' => 1, 'max' => 30]),
                self::field('row_key', 'refs', ['ref' => 'columns', 'min' => 1, 'max' => 5]),
            ]],
            'column'        => ['fields' => [
                self::field('key', 'key'),
                self::enum('type', ['text', 'money', 'integer', 'number']),
                self::optional(self::field('required', 'boolean'), false),
            ]],
            'service_rate'  => self::serviceRate(),
            'tier'          => ['fields' => [
                self::field('up_to', 'choice', ['nullable' => true, 'choices' => [
                    'value'  => ['type' => 'integer', 'min' => 1, 'max' => 100000],
                    'column' => ['type' => 'ref', 'ref' => 'integer_columns'],
                ]]),
                self::field('price', 'choice', ['choices' => self::moneyOrColumn()]),
            ]],
            'per_meter'     => ['fields' => self::perMeter()],
            'fixed_rate'    => ['fields' => [self::field('amount', 'choice', ['choices' => self::moneyOrColumn()])]],
            'fee'           => self::fee(),
            'window'        => ['fields' => [self::field('from', 'time'), self::field('to', 'time')]],
            'threshold'     => ['fields' => [self::field('over', 'number'), self::field('amount', 'money')]],
            'subtotal_fee'  => self::subtotalFee(),
            'manual_charge' => self::manualCharge(),
            'entered'       => ['fields' => [self::field('minimum', 'money'), self::field('maximum', 'money')]],
            'quantity'      => ['fields' => [
                self::field('unit', 'text'),
                self::field('step', 'number'),
                self::field('maximum', 'number'),
            ]],
            'holiday_list'  => ['fields' => [self::field('dates', 'dates', ['max' => 400])]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function attribute(): array
    {
        return [
            'discriminator' => 'type',
            'fields'        => [
                self::field('key', 'key'),
                self::field('name', 'text'),
                self::enum('scope', ['order', 'entity']),
                self::optional(self::field('required', 'boolean'), false),
                self::optional(self::field('required_for', 'refs', ['ref' => 'service_rates', 'max' => 20]), []),
            ],
            'variants'      => [
                'boolean' => [],
                'choice'  => [self::field('choices', 'list', ['of' => 'choice', 'min' => 1, 'max' => 50])],
                'number'  => [
                    self::optional(self::field('unit', 'text', ['nullable' => true]), null),
                    self::optional(self::field('maximum', 'number'), '1000000'),
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function serviceRate(): array
    {
        return [
            'discriminator' => 'method',
            'fields'        => [
                self::field('key', 'key'),
                self::field('name', 'text'),
                self::field('product', 'ref', ['ref' => 'products']),
                self::optional(self::field('rate_table', 'ref', ['ref' => 'rate_tables', 'nullable' => true]), null),
                self::field('line_name', 'choice', ['choices' => [
                    'text'   => ['type' => 'text'],
                    'column' => ['type' => 'ref', 'ref' => 'text_columns'],
                ]]),
                self::optional(self::field('billing_code_column', 'ref', ['ref' => 'text_columns', 'nullable' => true]), null),
                self::optional(self::field('minimum', 'money', ['nullable' => true]), null),
                self::optional(self::field('enabled', 'boolean'), true),
            ],
            'variants'      => [
                'tiers'      => [self::field('tiers', 'list', ['of' => 'tier', 'min' => 1, 'max' => 10])],
                'per_meter'  => [self::field('per_meter', 'object', ['of' => 'per_meter'])],
                'fixed_rate' => [self::field('fixed_rate', 'object', ['of' => 'fixed_rate'])],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function perMeter(): array
    {
        return [
            self::field('rate', 'money'),
            self::optional(self::field('base_fee', 'money'), 0),
            self::optional(self::field('minimum_distance', 'number'), '0'),
            self::field('maximum_distance', 'number', ['max' => ProfileShapes::MAX_DISTANCE]),
            self::enum('rounding', ['up', 'nearest', 'none']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function fee(): array
    {
        $amount = [
            self::field('amount', 'money', ['nullable' => true]),
            self::optional(self::enum('if_missing', ['stop', 'skip']), 'stop'),
        ];

        return [
            'discriminator' => 'kind',
            'fields'        => array_merge(self::rule(), [
                self::field('applies_to', 'refs', ['ref' => 'products', 'min' => 1, 'max' => 50]),
            ], self::limits()),
            'variants'      => [
                'peak_hours' => array_merge([self::enum('charge', ['per_entity', 'per_order'])], $amount, [
                    self::optional(self::field('window', 'object', ['of' => 'window', 'nullable' => true]), null),
                    self::optional(self::field('weekdays', 'weekdays', ['nullable' => true]), null),
                    self::optional(
                        self::field('holidays', 'ref', ['ref' => 'holiday_lists', 'nullable' => true]),
                        null,
                    ),
                ]),
                'parcel'     => [
                    self::enum('charge', ['per_entity', 'per_order', 'per_unit']),
                    self::enum('basis', ['weight', 'volume']),
                    self::enum('measure', ['actual', 'dimensional', 'greater'], ['when' => ['basis' => ['weight']]]),
                    self::field('divisor', 'number', ['when' => ['measure' => ['dimensional', 'greater']]]),
                    self::enum('unit', array_merge(ProfileShapes::WEIGHT_UNITS, ProfileShapes::VOLUME_UNITS)),
                    self::enum('scope', ['entity', 'order']),
                    self::field('thresholds', 'list', ['of' => 'threshold', 'min' => 1, 'max' => 10]),
                ],
                'additional' => array_merge([self::enum('charge', ['per_entity', 'per_order', 'per_unit'])], $amount, [
                    self::field('attribute', 'ref', ['ref' => 'attributes']),
                    self::optional(self::field('equals', 'ref', ['ref' => 'choices', 'nullable' => true]), null),
                ]),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function subtotalFee(): array
    {
        $lists = [
            self::field('applies_to', 'refs', ['ref' => 'products', 'min' => 1, 'max' => 50]),
            self::optional(self::field('requires', 'refs', ['ref' => 'products', 'max' => 50]), []),
            self::optional(self::field('skipped_for', 'refs', ['ref' => 'products', 'max' => 50]), []),
        ];
        $missing = self::optional(self::enum('if_missing', ['stop', 'skip']), 'stop');

        return [
            'discriminator' => 'mode',
            'fields'        => array_merge(self::rule(), $lists, self::limits()),
            'variants'      => [
                'percentage' => [
                    self::optional(self::field('percentage', 'percent', ['nullable' => true]), null),
                    self::enum('charge', ['per_order']),
                    $missing,
                ],
                'flat'       => [
                    self::optional(self::field('amount', 'money', ['nullable' => true]), null),
                    self::enum('charge', ['per_order', 'per_line', 'per_entity']),
                    $missing,
                ],
            ],
        ];
    }

    /**
     * A manual charge's mode is which of amount and entered it sets, so the form offers it as a
     * variant even though the profile has no field for it.
     *
     * @return array<string, mixed>
     */
    private static function manualCharge(): array
    {
        return [
            'discriminator' => null,
            'fields'        => [
                self::field('key', 'key'),
                self::field('name', 'text'),
                self::field('product', 'ref', ['ref' => 'products']),
                self::optional(self::field('offered', 'boolean'), true),
                self::optional(self::field('effective_from', 'date', ['nullable' => true]), null),
                self::optional(self::field('effective_until', 'date', ['nullable' => true]), null),
            ],
            'variants'      => [
                'amount'  => [
                    self::field('amount', 'money'),
                    self::optional(self::field('quantity', 'object', ['of' => 'quantity', 'nullable' => true]), null),
                ],
                'entered' => [self::field('entered', 'object', ['of' => 'entered'])],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function rule(): array
    {
        return [
            self::field('key', 'key'),
            self::field('name', 'text'),
            self::field('product', 'ref', ['ref' => 'products']),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function limits(): array
    {
        return [
            self::optional(self::field('minimum', 'money', ['nullable' => true]), null),
            self::optional(self::field('maximum', 'money', ['nullable' => true]), null),
            self::optional(self::field('effective_from', 'date', ['nullable' => true]), null),
            self::optional(self::field('effective_until', 'date', ['nullable' => true]), null),
            self::optional(self::field('enabled', 'boolean'), true),
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    private static function moneyOrColumn(): array
    {
        return ['value' => ['type' => 'money'], 'column' => ['type' => 'ref', 'ref' => 'money_columns']];
    }

    /**
     * @param array<string, mixed> $extra
     *
     * @return array<string, mixed>
     */
    private static function field(string $name, string $type, array $extra = []): array
    {
        return ['name' => $name, 'type' => $type] + $extra;
    }

    /**
     * @param list<string>         $options
     * @param array<string, mixed> $extra
     *
     * @return array<string, mixed>
     */
    private static function enum(string $name, array $options, array $extra = []): array
    {
        return self::field($name, 'enum', ['options' => $options] + $extra);
    }

    /**
     * @param array<string, mixed> $field
     *
     * @return array<string, mixed>
     */
    private static function optional(array $field, mixed $default): array
    {
        return $field + ['optional' => true, 'default' => $default];
    }
}
