<?php

namespace Fleetbase\EnhancedPricing\Profile;

use Fleetbase\EnhancedPricing\Profile\Schema as S;

/**
 * Checks between the parts of a profile whose shape is already valid: unique keys, references to
 * products, columns, attributes and lists, the rule order of product lists, and values that must
 * agree with each other.
 */
final class ProfileReferences
{
    /** @var array<string, true> */
    private array $products = [];

    /** @var array<string, array<string, mixed>> */
    private array $tables = [];

    private readonly RuleKindReferences $kinds;

    /**
     * @param array<string, mixed> $profile a profile whose shape has no problems
     */
    public function __construct(private readonly S $schema, private readonly array $profile)
    {
        $this->kinds = new RuleKindReferences($schema, $profile);
    }

    public function check(): void
    {
        $this->products = $this->unique(self::items($this->profile['products']), 'code', 'products');
        $sections       = [
            'attributes',
            'entity_types',
            'rate_tables',
            'service_rates',
            'fees',
            'subtotal_fees',
            'manual_charges',
        ];
        foreach ($sections as $section) {
            $this->unique(self::items($this->profile[$section]), 'key', $section);
        }
        foreach (self::items($this->profile['rate_tables']) as $table) {
            $this->tables[self::text($table['key'])] = $table;
        }

        $this->rateTables();
        $this->attributes();
        $this->entityTypes();
        foreach (self::items($this->profile['service_rates']) as $index => $rate) {
            $this->serviceRate($rate, "service_rates.$index");
        }
        $this->rules();
        foreach (self::items($this->profile['manual_charges']) as $index => $charge) {
            $this->product($charge['product'], "manual_charges.$index.product");
            $this->kinds->manualCharge($charge, "manual_charges.$index");
        }
    }

    private function rateTables(): void
    {
        foreach (self::items($this->profile['rate_tables']) as $index => $table) {
            $columns = $this->unique(self::items($table['columns']), 'key', "rate_tables.$index.columns");
            foreach (self::strings($table['row_key']) as $position => $key) {
                $path = "rate_tables.$index.row_key.$position";
                if (isset($columns[$key]) === false) {
                    $this->schema->add(Problem::atPath(
                        $path,
                        'unknown-reference',
                        ['kind' => 'column', 'key' => $key],
                    ));
                } elseif (($this->column(self::text($table['key']), $key)['required'] ?? false) !== true) {
                    $this->schema->add(Problem::atPath($path, 'row-key-not-required', ['column' => $key]));
                }
            }
        }
    }

    private function attributes(): void
    {
        $rates = $this->keys('service_rates');
        foreach (self::items($this->profile['attributes']) as $index => $attribute) {
            if (isset($attribute['choices'])) {
                $this->unique(self::items($attribute['choices']), 'key', "attributes.$index.choices");
            }
            if (is_array($attribute['required'])) {
                foreach (self::strings($attribute['required']) as $position => $key) {
                    if (isset($rates[$key]) === false) {
                        $this->schema->add(Problem::atPath(
                            "attributes.$index.required.$position",
                            'unknown-reference',
                            ['kind' => 'service rate', 'key' => $key],
                        ));
                    }
                }
            }
        }
    }

    private function entityTypes(): void
    {
        $types   = $this->keys('entity_types');
        $default = self::text($this->profile['default_entity_type']);
        if (isset($types[$default]) === false) {
            $this->schema->add(Problem::atPath(
                'default_entity_type',
                'unknown-reference',
                ['kind' => 'entity type', 'key' => $default],
            ));
        }
        foreach (self::items($this->profile['entity_types']) as $index => $type) {
            if (is_array($type['own_price']) === false) {
                continue;
            }
            $ownPrice = $type['own_price'];
            $this->product($ownPrice['product'], "entity_types.$index.own_price.product");
            foreach (self::items($this->profile['service_rates']) as $rate) {
                if (
                    $rate['method'] === 'tiers'
                    && is_string($rate['rate_table'])
                    && isset($this->tables[$rate['rate_table']])
                ) {
                    $this->columnOfType(
                        $rate['rate_table'],
                        self::text($ownPrice['column']),
                        'money',
                        "entity_types.$index.own_price.column",
                    );
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $rate
     */
    private function serviceRate(array $rate, string $path): void
    {
        $this->product($rate['product'], "$path.product");
        $table = $rate['rate_table'];
        if (is_string($table) && isset($this->tables[$table]) === false) {
            $this->schema->add(Problem::atPath(
                "$path.rate_table",
                'unknown-reference',
                ['kind' => 'rate table', 'key' => $table],
            ));

            return;
        }
        if ($rate['method'] === 'tiers' && $table === null && $this->hasOwnPriceTypes()) {
            $this->schema->add(Problem::atPath("$path.rate_table", 'tiers-need-rate-table'));
        }
        $this->reference($rate['line_name'], is_string($table) ? $table : null, 'text', "$path.line_name");
        match ($rate['method']) {
            'tiers'      => $this->tiers(self::items($rate['tiers']), is_string($table) ? $table : null, "$path.tiers"),
            'per_meter'  => $this->kinds->limits(
                self::map($rate['per_meter']),
                'minimum_distance',
                'maximum_distance',
                "$path.per_meter",
            ),
            default      => $this->reference(
                self::map($rate['fixed_rate'])['amount'] ?? null,
                is_string($table) ? $table : null,
                'money',
                "$path.fixed_rate.amount",
            ),
        };
    }

    /**
     * @param list<array<string, mixed>> $tiers
     */
    private function tiers(array $tiers, ?string $table, string $path): void
    {
        $previous = 0;
        $last     = count($tiers) - 1;
        foreach ($tiers as $index => $tier) {
            $upTo = $tier['up_to'];
            if ($upTo === null && $index !== $last) {
                $this->schema->add(Problem::atPath("$path.$index.up_to", 'open-tier-not-last'));
            }
            if (is_array($upTo) && is_int($upTo['value'] ?? null)) {
                if ($upTo['value'] <= $previous) {
                    $this->schema->add(Problem::atPath("$path.$index.up_to", 'not-increasing'));
                }
                $previous = $upTo['value'];
            }
            $this->reference($upTo, $table, 'integer', "$path.$index.up_to");
            $this->reference($tier['price'], $table, 'money', "$path.$index.price");
        }
    }

    /**
     * Fees and subtotal fees: products, the rule order of their product lists, and their own
     * consistency checks.
     */
    private function rules(): void
    {
        $fees      = self::items($this->profile['fees']);
        $subtotals = self::items($this->profile['subtotal_fees']);
        $later     = [];
        foreach ($subtotals as $subtotal) {
            $later[] = self::text($subtotal['product']);
        }
        // Each rule's "later" products are those of the rules after it, so walk backward.
        $laterOf = [];
        for ($index = count($subtotals) - 1; $index >= 0; $index--) {
            $laterOf["subtotal_fees.$index"] = array_slice($later, $index + 1);
        }
        $feeProducts = array_map(static fn (array $fee): string => self::text($fee['product']), $fees);
        foreach ($fees as $index => $fee) {
            $laterOf["fees.$index"] = array_merge(array_slice($feeProducts, $index + 1), $later);
        }

        foreach ($fees as $index => $fee) {
            $path = "fees.$index";
            $this->ruleCommon($fee, $path, ['applies_to'], $laterOf[$path]);
            $this->kinds->fee($fee, $path);
        }
        foreach ($subtotals as $index => $subtotal) {
            $path = "subtotal_fees.$index";
            $this->ruleCommon($subtotal, $path, ['applies_to', 'requires', 'skipped_for'], $laterOf[$path]);
            $this->kinds->subtotal($subtotal, $path);
        }
    }

    /**
     * @param array<string, mixed> $rule
     * @param list<string>         $lists
     * @param list<string>         $later
     */
    private function ruleCommon(array $rule, string $path, array $lists, array $later): void
    {
        $own = self::text($rule['product']);
        $this->product($own, "$path.product");
        $later = array_flip($later);
        $seen  = [];
        foreach ($lists as $list) {
            foreach (self::strings($rule[$list]) as $position => $code) {
                $where = "$path.$list.$position";
                if (isset($this->products[$code]) === false) {
                    $this->schema->add(Problem::atPath($where, 'unknown-product', ['code' => $code]));
                } elseif ($code === $own) {
                    $this->schema->add(Problem::atPath($where, 'own-product', ['code' => $code]));
                } elseif (isset($later[$code])) {
                    $this->schema->add(Problem::atPath($where, 'later-product', ['code' => $code]));
                } elseif (isset($seen[$code]) && $seen[$code] === $list) {
                    $this->schema->add(Problem::atPath($where, 'duplicate', ['value' => $code]));
                } elseif (isset($seen[$code])) {
                    $this->schema->add(Problem::atPath($where, 'product-in-two-lists', ['code' => $code]));
                }
                $seen[$code] ??= $list;
            }
        }
        $this->kinds->limits($rule, 'minimum', 'maximum', $path);
        $from  = $rule['effective_from'] ?? null;
        $until = $rule['effective_until'] ?? null;
        if (is_string($from) && is_string($until) && $until < $from) {
            $this->schema->add(Problem::atPath("$path.effective_until", 'until-before-from'));
        }
    }

    /**
     * A column reference must name a column of the rate table, of the given type.
     */
    private function reference(mixed $reference, ?string $table, string $type, string $path): void
    {
        if (is_array($reference) === false || isset($reference['column']) === false) {
            return;
        }
        if ($table === null) {
            $this->schema->add(Problem::atPath("$path.column", 'column-needs-rate-table'));

            return;
        }
        $this->columnOfType($table, self::text($reference['column']), $type, "$path.column");
    }

    private function columnOfType(string $table, string $column, string $type, string $path): void
    {
        $definition = $this->column($table, $column);
        if ($definition === null) {
            $this->schema->add(Problem::atPath($path, 'unknown-reference', ['kind' => 'column', 'key' => $column]));
        } elseif ($definition['type'] !== $type) {
            $this->schema->add(Problem::atPath($path, 'column-wrong-type', ['column' => $column, 'expected' => $type]));
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function column(string $table, string $column): ?array
    {
        foreach (self::items($this->tables[$table]['columns'] ?? []) as $definition) {
            if ($definition['key'] === $column) {
                return $definition;
            }
        }

        return null;
    }

    private function hasOwnPriceTypes(): bool
    {
        foreach (self::items($this->profile['entity_types']) as $type) {
            if (is_array($type['own_price'])) {
                return true;
            }
        }

        return false;
    }

    private function product(mixed $code, string $path): void
    {
        if (is_string($code) && isset($this->products[$code]) === false) {
            $this->schema->add(Problem::atPath($path, 'unknown-product', ['code' => $code]));
        }
    }

    /**
     * Reports repeated values of a field and returns the values seen.
     *
     * @param list<array<string, mixed>> $items
     *
     * @return array<string, true>
     */
    private function unique(array $items, string $field, string $path): array
    {
        $seen = [];
        foreach ($items as $index => $item) {
            $value = self::text($item[$field] ?? '');
            if (isset($seen[$value])) {
                $this->schema->add(Problem::atPath("$path.$index.$field", 'duplicate', ['value' => $value]));
            }
            $seen[$value] = true;
        }

        return $seen;
    }

    /**
     * @return array<string, true>
     */
    private function keys(string $section): array
    {
        $keys = [];
        foreach (self::items($this->profile[$section]) as $item) {
            $keys[self::text($item['key'])] = true;
        }

        return $keys;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function items(mixed $value): array
    {
        $items = [];
        foreach (is_array($value) ? $value : [] as $item) {
            if (is_array($item)) {
                $items[] = self::map($item);
            }
        }

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    public static function map(mixed $value): array
    {
        /** @var array<string, mixed> $map */
        $map = is_array($value) ? $value : [];

        return $map;
    }

    /**
     * @return list<string>
     */
    public static function strings(mixed $value): array
    {
        return array_values(array_filter(is_array($value) ? $value : [], 'is_string'));
    }

    public static function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
