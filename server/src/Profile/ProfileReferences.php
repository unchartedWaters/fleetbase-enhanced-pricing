<?php

namespace Fleetbase\EnhancedPricing\Profile;

use Fleetbase\EnhancedPricing\Profile\Schema as S;
use Fleetbase\EnhancedPricing\Support\Exact;

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

    /**
     * @param array<string, mixed> $profile a profile whose shape has no problems
     */
    public function __construct(private readonly S $schema, private readonly array $profile)
    {
    }

    public function check(): void
    {
        $this->products = $this->unique(self::items($this->profile['products']), 'code', 'products');
        foreach (['attributes', 'entity_types', 'rate_tables', 'service_rates', 'fees', 'subtotal_fees', 'manual_charges'] as $section) {
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
            $this->manualCharge($charge, "manual_charges.$index");
        }
    }

    private function rateTables(): void
    {
        foreach (self::items($this->profile['rate_tables']) as $index => $table) {
            $columns = $this->unique(self::items($table['columns']), 'key', "rate_tables.$index.columns");
            foreach (self::strings($table['row_key']) as $position => $key) {
                $path = "rate_tables.$index.row_key.$position";
                if (isset($columns[$key]) === false) {
                    $this->schema->add(Problem::at($path, 'unknown-reference', ['kind' => 'column', 'key' => $key]));
                } elseif (($this->column(self::text($table['key']), $key)['required'] ?? false) !== true) {
                    $this->schema->add(Problem::at($path, 'row-key-not-required', ['column' => $key]));
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
                        $this->schema->add(Problem::at("attributes.$index.required.$position", 'unknown-reference', ['kind' => 'service rate', 'key' => $key]));
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
            $this->schema->add(Problem::at('default_entity_type', 'unknown-reference', ['kind' => 'entity type', 'key' => $default]));
        }
        foreach (self::items($this->profile['entity_types']) as $index => $type) {
            if ($type['priced'] !== 'column') {
                continue;
            }
            $this->product($type['product'], "entity_types.$index.product");
            foreach (self::items($this->profile['service_rates']) as $rate) {
                if ($rate['method'] === 'tiers' && is_string($rate['rate_table']) && isset($this->tables[$rate['rate_table']])) {
                    $this->columnOfType($rate['rate_table'], self::text($type['column']), 'money', "entity_types.$index.column");
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
            $this->schema->add(Problem::at("$path.rate_table", 'unknown-reference', ['kind' => 'rate table', 'key' => $table]));

            return;
        }
        if ($rate['method'] === 'tiers' && $table === null && $this->hasColumnTypes()) {
            $this->schema->add(Problem::at("$path.rate_table", 'tiers-need-rate-table'));
        }
        $this->reference($rate['line_name'], is_string($table) ? $table : null, 'text', "$path.line_name");
        match ($rate['method']) {
            'tiers'      => $this->tiers(self::items($rate['tiers']), is_string($table) ? $table : null, "$path.tiers"),
            'per_meter'  => $this->limits(self::map($rate['per_meter']), 'minimum_distance', 'maximum_distance', "$path.per_meter"),
            default      => $this->reference(self::map($rate['fixed_rate'])['amount'] ?? null, is_string($table) ? $table : null, 'money', "$path.fixed_rate.amount"),
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
                $this->schema->add(Problem::at("$path.$index.up_to", 'open-tier-not-last'));
            }
            if (is_array($upTo) && is_int($upTo['value'] ?? null)) {
                if ($upTo['value'] <= $previous) {
                    $this->schema->add(Problem::at("$path.$index.up_to", 'not-increasing'));
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
            match ($fee['kind']) {
                'peak_hours' => $this->peakHours($fee, $path),
                'parcel'     => $this->parcel($fee, $path),
                default      => $this->additional($fee, $path),
            };
        }
        foreach ($subtotals as $index => $subtotal) {
            $path = "subtotal_fees.$index";
            $this->ruleCommon($subtotal, $path, ['applies_to', 'requires', 'skipped_for'], $laterOf[$path]);
            $this->subtotal($subtotal, $path);
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
                $at = "$path.$list.$position";
                if (isset($this->products[$code]) === false) {
                    $this->schema->add(Problem::at($at, 'unknown-product', ['code' => $code]));
                } elseif ($code === $own) {
                    $this->schema->add(Problem::at($at, 'own-product', ['code' => $code]));
                } elseif (isset($later[$code])) {
                    $this->schema->add(Problem::at($at, 'later-product', ['code' => $code]));
                } elseif (isset($seen[$code]) && $seen[$code] === $list) {
                    $this->schema->add(Problem::at($at, 'duplicate', ['value' => $code]));
                } elseif (isset($seen[$code])) {
                    $this->schema->add(Problem::at($at, 'product-in-two-lists', ['code' => $code]));
                }
                $seen[$code] ??= $list;
            }
        }
        $this->limits($rule, 'minimum', 'maximum', $path);
        $from  = $rule['effective_from'] ?? null;
        $until = $rule['effective_until'] ?? null;
        if (is_string($from) && is_string($until) && $until < $from) {
            $this->schema->add(Problem::at("$path.effective_until", 'until-before-from'));
        }
    }

    /**
     * @param array<string, mixed> $fee
     */
    private function peakHours(array $fee, string $path): void
    {
        $this->charge($fee, ['per_entity', 'per_order'], $path);
        if ($fee['window'] === null && $fee['weekdays'] === null && $fee['holidays'] === null) {
            $this->schema->add(Problem::at($path, 'needs-one-of', ['fields' => 'window, weekdays, holidays']));
        }
        $window = $fee['window'];
        if (is_array($window) && $window['from'] === $window['to']) {
            $this->schema->add(Problem::at("$path.window.to", 'window-empty'));
        }
        $holidays = $fee['holidays'];
        if (is_string($holidays) && array_key_exists($holidays, self::map($this->profile['holiday_lists'])) === false) {
            $this->schema->add(Problem::at("$path.holidays", 'unknown-reference', ['kind' => 'holiday list', 'key' => $holidays]));
        }
    }

    /**
     * @param array<string, mixed> $fee
     */
    private function parcel(array $fee, string $path): void
    {
        $this->charge($fee, $fee['scope'] === 'entity' ? ['per_entity', 'per_unit'] : ['per_order', 'per_unit'], $path);
        $weight = $fee['basis'] === 'weight';
        $units  = $weight ? ProfileShapes::WEIGHT_UNITS : ProfileShapes::VOLUME_UNITS;
        if (in_array($fee['unit'], $units, true) === false) {
            $this->schema->add(Problem::at("$path.unit", 'not-one-of', ['choices' => implode(', ', $units)]));
        }
        $measure = $fee['measure'];
        if ($weight && $measure === null) {
            $this->schema->add(Problem::at("$path.measure", 'missing-field', ['field' => 'measure']));
        }
        if ($weight === false && $measure !== null) {
            $this->schema->add(Problem::at("$path.measure", 'measure-not-allowed'));
        }
        $needsDivisor = in_array($measure, ['dimensional', 'greater'], true);
        if ($needsDivisor && $fee['divisor'] === null) {
            $this->schema->add(Problem::at("$path.divisor", 'missing-field', ['field' => 'divisor']));
        }
        if ($needsDivisor === false && $fee['divisor'] !== null) {
            $this->schema->add(Problem::at("$path.divisor", 'divisor-not-allowed'));
        }
        $previous = null;
        foreach (self::items($fee['thresholds']) as $index => $threshold) {
            $over = Exact::of(self::text($threshold['over']));
            if ($previous !== null && $over->isLessThanOrEqualTo($previous)) {
                $this->schema->add(Problem::at("$path.thresholds.$index.over", 'not-increasing'));
            }
            $previous = $over;
        }
    }

    /**
     * @param array<string, mixed> $fee
     */
    private function additional(array $fee, string $path): void
    {
        $this->charge($fee, ['per_entity', 'per_order', 'per_unit'], $path);
        $attribute = null;
        foreach (self::items($this->profile['attributes']) as $candidate) {
            if ($candidate['key'] === $fee['attribute']) {
                $attribute = $candidate;
            }
        }
        if ($attribute === null) {
            $this->schema->add(Problem::at("$path.attribute", 'unknown-reference', ['kind' => 'attribute', 'key' => self::text($fee['attribute'])]));

            return;
        }
        if ($attribute['type'] === 'choice') {
            $choices = array_map(static fn (array $choice): string => self::text($choice['key']), self::items($attribute['choices']));
            if ($fee['equals'] === null) {
                $this->schema->add(Problem::at("$path.equals", 'missing-field', ['field' => 'equals']));
            } elseif (in_array($fee['equals'], $choices, true) === false) {
                $this->schema->add(Problem::at("$path.equals", 'unknown-reference', ['kind' => 'choice', 'key' => self::text($fee['equals'])]));
            }
        } elseif ($fee['equals'] !== null) {
            $this->schema->add(Problem::at("$path.equals", 'equals-not-allowed'));
        }
        if ($fee['charge'] === 'per_unit' && $attribute['type'] !== 'number') {
            $this->schema->add(Problem::at("$path.charge", 'per-unit-needs-number'));
        }
    }

    /**
     * @param array<string, mixed> $subtotal
     */
    private function subtotal(array $subtotal, string $path): void
    {
        $percentage = $subtotal['mode'] === 'percentage';
        if ($percentage && $subtotal['amount'] !== null) {
            $this->schema->add(Problem::at("$path.amount", 'not-allowed-for-mode', ['mode' => 'percentage']));
        }
        if ($percentage === false && $subtotal['percentage'] !== null) {
            $this->schema->add(Problem::at("$path.percentage", 'not-allowed-for-mode', ['mode' => 'flat']));
        }
        if ($percentage && $subtotal['charge'] !== 'per_order') {
            $this->schema->add(Problem::at("$path.charge", 'not-one-of', ['choices' => 'per_order']));
        }
    }

    /**
     * @param array<string, mixed> $charge
     */
    private function manualCharge(array $charge, string $path): void
    {
        $this->product($charge['product'], "$path.product");
        if (($charge['amount'] === null) === ($charge['entered'] === null)) {
            $this->schema->add(Problem::at($path, 'needs-exactly-one', ['fields' => 'amount, entered']));
        }
        if ($charge['entered'] !== null) {
            if ($charge['quantity'] !== null) {
                $this->schema->add(Problem::at("$path.quantity", 'entered-takes-no-quantity'));
            }
            $this->limits(self::map($charge['entered']), 'minimum', 'maximum', "$path.entered");
        }
        $quantity = $charge['quantity'];
        if (is_array($quantity)) {
            $steps = Exact::of(self::text($quantity['maximum']))->dividedBy(self::text($quantity['step']));
            if (Exact::isWhole($steps) === false) {
                $this->schema->add(Problem::at("$path.quantity.maximum", 'not-a-step'));
            }
        }
        $from  = $charge['effective_from'];
        $until = $charge['effective_until'];
        if (is_string($from) && is_string($until) && $until < $from) {
            $this->schema->add(Problem::at("$path.effective_until", 'until-before-from'));
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
            $this->schema->add(Problem::at("$path.column", 'column-needs-rate-table'));

            return;
        }
        $this->columnOfType($table, self::text($reference['column']), $type, "$path.column");
    }

    private function columnOfType(string $table, string $column, string $type, string $path): void
    {
        $definition = $this->column($table, $column);
        if ($definition === null) {
            $this->schema->add(Problem::at($path, 'unknown-reference', ['kind' => 'column', 'key' => $column]));
        } elseif ($definition['type'] !== $type) {
            $this->schema->add(Problem::at($path, 'column-wrong-type', ['column' => $column, 'expected' => $type]));
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

    private function hasColumnTypes(): bool
    {
        foreach (self::items($this->profile['entity_types']) as $type) {
            if ($type['priced'] === 'column') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $rule
     * @param list<string>         $allowed
     */
    private function charge(array $rule, array $allowed, string $path): void
    {
        if (in_array($rule['charge'], $allowed, true) === false) {
            $this->schema->add(Problem::at("$path.charge", 'not-one-of', ['choices' => implode(', ', $allowed)]));
        }
    }

    /**
     * Reports a minimum above its maximum, for money or numbers.
     *
     * @param array<string, mixed> $values
     */
    private function limits(array $values, string $minimum, string $maximum, string $path): void
    {
        $low  = $values[$minimum] ?? null;
        $high = $values[$maximum] ?? null;
        if ((is_int($low) || is_string($low)) && (is_int($high) || is_string($high))
            && Exact::of($low)->isGreaterThan(Exact::of($high))) {
            $this->schema->add(Problem::at(S::join($path, $minimum), 'minimum-above-maximum'));
        }
    }

    private function product(mixed $code, string $path): void
    {
        if (is_string($code) && isset($this->products[$code]) === false) {
            $this->schema->add(Problem::at($path, 'unknown-product', ['code' => $code]));
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
                $this->schema->add(Problem::at("$path.$index.$field", 'duplicate', ['value' => $value]));
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
                $items[] = $item;
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
