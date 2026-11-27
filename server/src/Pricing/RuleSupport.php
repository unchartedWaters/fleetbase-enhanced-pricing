<?php

namespace Fleetbase\EnhancedPricing\Pricing;

use Fleetbase\EnhancedPricing\Profile\ProfileReferences as R;

/**
 * What fees and subtotal fees share: the entities they count, their amount when it may be
 * missing, and their minimum and maximum.
 */
final class RuleSupport
{
    public function __construct(private readonly OrderContext $order)
    {
    }

    /**
     * Entities billed under one of the rule's products, on a line of more than $0.
     *
     * @param array<string, mixed> $rule
     *
     * @return list<PricedEntity>
     */
    public function counted(array $rule): array
    {
        $products = R::strings($rule['applies_to']);

        return array_values(array_filter(
            $this->order->entities,
            fn (PricedEntity $entity): bool => in_array($entity->billingProduct, $products, true)
                && $this->order->book->hasPositive($entity->billingProduct),
        ));
    }

    /**
     * The rule's amount: a number, or a money column of the order's rate card row. Null when it is
     * missing and the rule says skip.
     *
     * @param array<string, mixed> $rule
     */
    public function amount(array $rule, string $field = 'amount'): ?int
    {
        $amount = $rule[$field] ?? null;
        if (is_array($amount)) {
            $amount = $this->cell($rule, R::map($amount));
        }
        if (is_int($amount)) {
            return $amount;
        }
        if (($rule['if_missing'] ?? 'stop') === 'skip') {
            return null;
        }

        $params = ['rule' => R::text($rule['name']), 'rule_key' => R::text($rule['key'])];
        throw $this->order->fail('missing-amount', $params);
    }

    /**
     * The value of the reference's column in the order's row. Pricing stops (or the fee is left off,
     * with if_missing skip) when the order's rate card is not for the table the fee reads.
     *
     * @param array<string, mixed> $rule
     * @param array<string, mixed> $reference
     */
    private function cell(array $rule, array $reference): mixed
    {
        $table  = R::text($reference['rate_table'] ?? '');
        $column = R::text($reference['column'] ?? '');
        $name   = R::text($rule['name']);
        $skip   = ($rule['if_missing'] ?? 'stop') === 'skip';
        if ($this->order->row === null || ($this->order->serviceRate['rate_table'] ?? null) !== $table) {
            if ($skip) {
                return null;
            }

            throw $this->order->fail('row-from-other-table', ['rule' => $name, 'table' => $table]);
        }
        $value = $this->order->row[$column] ?? null;
        if ($value === null || $value === '') {
            if ($skip) {
                return null;
            }

            $params = ['rule' => $name, 'column' => $column, 'column_key' => $column];
            throw $this->order->fail('missing-row-amount', $params);
        }

        return $value;
    }

    /**
     * Applies the rule's minimum and maximum to its total. When either changes it, the rule's
     * lines become one line of quantity 1 at the limit. A rule whose lines total $0 makes none.
     *
     * @param array<string, mixed>                                                                 $rule
     * @param list<array{product: string, name: string, quantity: int, unit: int, source: string}> $drafts
     *
     * @return list<array{product: string, name: string, quantity: int, unit: int, source: string}>
     */
    public function limits(array $rule, array $drafts): array
    {
        $total = array_sum(array_map(static fn (array $draft): int => $draft['quantity'] * $draft['unit'], $drafts));
        if ($total <= 0) {
            return [];
        }
        $limited = $total;
        if (is_int($rule['minimum']) && $limited < $rule['minimum']) {
            $limited = $rule['minimum'];
        }
        if (is_int($rule['maximum']) && $limited > $rule['maximum']) {
            $limited = $rule['maximum'];
        }

        return $limited === $total ? $drafts : [$this->draft($rule, 1, $limited)];
    }

    /**
     * @param array<string, mixed> $rule
     *
     * @return array{
     *     product: string, billing_code?: string, name: string, quantity: int, unit: int, source: string
     * }
     */
    public function draft(array $rule, int $quantity, int $unit, ?string $name = null): array
    {
        $draft = [
            'product'  => R::text($rule['product']),
            'name'     => $name ?? R::text($rule['name']),
            'quantity' => $quantity,
            'unit'     => $unit,
            'source'   => (isset($rule['kind']) ? 'fee:' : 'subtotal_fee:') . R::text($rule['key']),
        ];
        // The rule's own code, when it has one; else LineBook bills under the product's code.
        if (is_string($rule['code'] ?? null)) {
            $draft['billing_code'] = $rule['code'];
        }

        return $draft;
    }
}
