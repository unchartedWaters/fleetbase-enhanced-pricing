<?php

namespace Fleetbase\EnhancedPricing\Pricing;

use Fleetbase\EnhancedPricing\Profile\ProfileReferences as R;
use Fleetbase\EnhancedPricing\Profile\Schema;
use Fleetbase\EnhancedPricing\Support\Exact;

/**
 * The service fee: tiers per entity, a per-meter rate, or a fixed rate, and the lines of entity
 * types with their own price. It also decides each entity's billing product.
 */
final class ServiceFee
{
    /** @var array<string, mixed> */
    private array $rate;

    private string $source;

    public function __construct(private readonly OrderContext $order, private readonly ?int $distanceMeters)
    {
        $this->rate   = $order->serviceRate;
        $this->source = 'service_rate:' . R::text($this->rate['key']);
    }

    /**
     * Checks a rate card row against its rate table: known columns, required ones present, and
     * every value of its column's type.
     *
     * @param array<string, mixed> $table
     * @param array<string, mixed> $row
     */
    public static function checkRow(OrderContext $order, array $table, array $row): void
    {
        $columns = [];
        foreach (R::items($table['columns']) as $column) {
            $columns[R::text($column['key'])] = $column;
        }
        foreach ($row as $key => $value) {
            $column = $columns[(string) $key] ?? null;
            $blank  = $value === null || $value === '';
            if ($column === null || ($blank === false && self::valueFits(R::text($column['type']), $value) === false)) {
                throw $order->fail('row-invalid', ['column' => (string) $key]);
            }
        }
        foreach ($columns as $key => $column) {
            if ($column['required'] === true && self::cellOf($row, $key) === null) {
                throw $order->fail('row-invalid', ['column' => $key]);
            }
        }
    }

    public function price(): void
    {
        $product = R::text($this->rate['product']);
        foreach ($this->order->entities as $entity) {
            $entity->billingProduct = $product;
        }
        $drafts = match ($this->rate['method']) {
            'tiers'     => $this->tiers(),
            'per_meter' => $this->perMeter(),
            default     => [
                $this->draft(
                    1,
                    $this->amount(R::map($this->rate['fixed_rate'])['amount'] ?? null, $this->ruleName()),
                    $this->lineName(),
                ),
            ],
        };

        $minimum = $this->rate['minimum'];
        $total   = array_sum(array_map(static fn (array $draft): int => $draft['quantity'] * $draft['unit'], $drafts));
        if (is_int($minimum) && $total > 0 && $total < $minimum) {
            $drafts = [$this->draft(1, $minimum, $this->lineName())];
        }
        $this->order->book->add($drafts);
        if ($this->rate['method'] === 'tiers') {
            $this->order->book->add($this->ownPriceTypes());
        }
    }

    /**
     * @return list<array{product: string, name: string, quantity: int, unit: int, source: string}>
     */
    private function tiers(): array
    {
        if ($this->order->entities === []) {
            throw $this->order->fail('no-entities');
        }
        $counted = count(array_filter(
            $this->order->entities,
            static fn (PricedEntity $entity): bool => $entity->type['own_price'] === null,
        ));
        $tiers   = R::items($this->rate['tiers']);
        $drafts  = [];
        $start   = 0;
        $name    = $this->lineName();
        foreach ($tiers as $tier) {
            $limit = $tier['up_to'] === null ? null : $this->tierLimit(R::map($tier['up_to']), $start);
            $end   = $limit === null ? $counted : min($counted, $limit);
            if ($end > $start) {
                $drafts[] = $this->draft(
                    $end - $start,
                    $this->amount($tier['price'], $this->ruleName()),
                    $name,
                );
            }
            $start = $limit ?? $counted;
        }
        if ($counted > $start) {
            $params = ['entities' => $counted, 'limit' => $start, 'rule' => $this->ruleName()];
            throw $this->order->fail('too-many-entities-for-tiers', $params);
        }

        return $drafts;
    }

    /**
     * @param array<string, mixed> $upTo
     */
    private function tierLimit(array $upTo, int $previous): int
    {
        $limit = $upTo['value'] ?? null;
        if (isset($upTo['column'])) {
            $column = R::text($upTo['column']);
            $limit  = $this->cell($column);
            if ($limit === null) {
                $missing = $this->order->fail('missing-tier-limit', [
                    'rule'   => $this->ruleName(),
                    'column' => $column,
                ]);
                throw $missing;
            }
        }
        if (is_int($limit) === false || $limit <= $previous) {
            throw $this->order->fail('tiers-invalid', ['rule' => $this->ruleName()]);
        }

        return $limit;
    }

    /**
     * @return list<array{product: string, name: string, quantity: int, unit: int, source: string}>
     */
    private function ownPriceTypes(): array
    {
        $drafts = [];
        foreach (R::items($this->order->profile['entity_types']) as $type) {
            if (is_array($type['own_price']) === false) {
                continue;
            }
            $ownPrice = R::map($type['own_price']);
            $entities = array_filter(
                $this->order->entities,
                static fn (PricedEntity $entity): bool => $entity->type['key'] === $type['key'],
            );
            if ($entities === []) {
                continue;
            }
            foreach ($entities as $entity) {
                $entity->billingProduct = R::text($ownPrice['product']);
            }
            $key      = R::text($type['key']);
            $drafts[] = [
                'product'  => R::text($ownPrice['product']),
                'name'     => R::text($type['name']),
                'quantity' => count($entities),
                'unit'     => $this->amount(['column' => $ownPrice['column']], R::text($type['name'])),
                'source'   => 'entity_type:' . $key,
            ];
        }

        return $drafts;
    }

    /**
     * @return list<array{product: string, name: string, quantity: int, unit: int, source: string}>
     */
    private function perMeter(): array
    {
        $settings = R::map($this->rate['per_meter']);
        $unit     = R::text($this->order->profile['distance_unit']);
        if ($this->distanceMeters === null || $this->distanceMeters <= 0) {
            throw $this->order->fail('distance-required');
        }
        $distance = Units::distance($this->distanceMeters, $unit);
        if ($distance->isGreaterThan(R::text($settings['maximum_distance']))) {
            $tooLong = $this->order->fail('distance-too-long', [
                'distance' => Exact::display($distance),
                'maximum'  => R::text($settings['maximum_distance']),
            ]);
            throw $tooLong;
        }
        $distance = match ($settings['rounding']) {
            'up'      => Exact::rational(Exact::ceil($distance)),
            'nearest' => Exact::rational(Exact::round($distance)),
            default   => $distance,
        };
        $minimum = Exact::rational(R::text($settings['minimum_distance']));
        if ($distance->isLessThan($minimum)) {
            $distance = $minimum;
        }
        $rate   = is_int($settings['rate']) ? $settings['rate'] : 0;
        $base   = is_int($settings['base_fee']) ? $settings['base_fee'] : 0;
        $amount = Exact::round($distance->multipliedBy($rate)->plus($base));

        return [$this->draft(1, $amount, sprintf('%s (%s %s)', $this->lineName(), Exact::display($distance), $unit))];
    }

    /**
     * An amount from { "value": minor units } or a money column of the row.
     */
    private function amount(mixed $reference, string $rule): int
    {
        $reference = R::map($reference);
        if (array_key_exists('value', $reference) && is_int($reference['value'])) {
            return $reference['value'];
        }
        $column = R::text($reference['column'] ?? '');
        $value  = $this->cell($column);
        if (is_int($value) === false) {
            throw $this->order->fail('missing-row-amount', ['rule' => $rule, 'column' => $column]);
        }

        return $value;
    }

    private function lineName(): string
    {
        $lineName = R::map($this->rate['line_name']);
        if (isset($lineName['text'])) {
            return R::text($lineName['text']);
        }
        $column = R::text($lineName['column'] ?? '');
        $value  = $this->cell($column);
        if (is_string($value) === false) {
            throw $this->order->fail('missing-value', ['rule' => $this->ruleName(), 'column' => $column]);
        }

        return $value;
    }

    /**
     * The service rate's name, which messages use to name it.
     */
    private function ruleName(): string
    {
        return R::text($this->rate['name']);
    }

    private function cell(string $column): mixed
    {
        return self::cellOf($this->order->row ?? [], $column);
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function cellOf(array $row, string $column): mixed
    {
        $value = $row[$column] ?? null;

        return $value === '' ? null : $value;
    }

    private static function valueFits(string $type, mixed $value): bool
    {
        return match ($type) {
            'text'    => is_string($value) && mb_strlen($value) <= Schema::MAX_TEXT,
            'money'   => is_int($value) && $value >= 0 && $value <= Schema::MAX_MONEY,
            'integer' => is_int($value) && $value >= 0 && $value <= 100000,
            default   => ($number = Exact::canonical($value)) !== null && str_starts_with($number, '-') === false,
        };
    }

    /**
     * @return array{product: string, name: string, quantity: int, unit: int, source: string}
     */
    private function draft(int $quantity, int $unit, string $name): array
    {
        return [
            'product'  => R::text($this->rate['product']),
            'name'     => $name,
            'quantity' => $quantity,
            'unit'     => $unit,
            'source'   => $this->source,
        ];
    }
}
