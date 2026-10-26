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
     * The rule's amount, or null when it is missing and the rule says skip.
     *
     * @param array<string, mixed> $rule
     */
    public function amount(array $rule, string $field = 'amount'): ?int
    {
        $amount = $rule[$field] ?? null;
        if (is_int($amount)) {
            return $amount;
        }
        if (($rule['if_missing'] ?? 'stop') === 'skip') {
            return null;
        }

        throw $this->order->fail('missing-amount', ['rule' => R::text($rule['name']), 'rule_key' => R::text($rule['key'])]);
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
     * @return array{product: string, name: string, quantity: int, unit: int, source: string}
     */
    public function draft(array $rule, int $quantity, int $unit, ?string $name = null): array
    {
        return [
            'product'  => R::text($rule['product']),
            'name'     => $name ?? R::text($rule['name']),
            'quantity' => $quantity,
            'unit'     => $unit,
            'source'   => (isset($rule['kind']) ? 'fee:' : 'subtotal_fee:') . R::text($rule['key']),
        ];
    }
}
