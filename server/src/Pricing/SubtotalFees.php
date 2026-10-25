<?php

namespace Fleetbase\EnhancedPricing\Pricing;

use Fleetbase\EnhancedPricing\Profile\ProfileReferences as R;
use Fleetbase\EnhancedPricing\Support\Exact;

/**
 * Subtotal fees, last, in the profile's order: a percentage of the lines for their chosen
 * products, or a flat amount, when the products they require are on the order and the ones they
 * are skipped for are not.
 */
final class SubtotalFees
{
    private RuleSupport $support;

    public function __construct(private readonly OrderContext $order)
    {
        $this->support = new RuleSupport($order);
    }

    public function price(): void
    {
        $book = $this->order->book;
        foreach (R::items($this->order->profile['subtotal_fees']) as $fee) {
            if ($fee['enabled'] !== true || $this->order->inEffect($fee) === false) {
                continue;
            }
            $chosen = R::strings($fee['applies_to']);
            if ($book->hasAny($chosen) === false || $book->hasAny(R::strings($fee['skipped_for']))) {
                continue;
            }
            foreach (R::strings($fee['requires']) as $required) {
                if ($book->hasPositive($required) === false) {
                    continue 2;
                }
            }
            $book->add($this->support->limits($fee, $this->drafts($fee, $chosen)));
        }
    }

    /**
     * @param array<string, mixed> $fee
     * @param list<string>         $chosen
     *
     * @return list<array{product: string, name: string, quantity: int, unit: int, source: string}>
     */
    private function drafts(array $fee, array $chosen): array
    {
        if ($fee['mode'] === 'percentage') {
            $percentage = $this->support->amount($fee, 'percentage');
            if ($percentage === null) {
                return [];
            }
            $amount = Exact::round(
                Exact::rational($this->order->book->sum($chosen))->multipliedBy($percentage)->dividedBy(10000),
            );

            return [$this->support->draft($fee, 1, $amount)];
        }
        $amount = $this->support->amount($fee);
        if ($amount === null) {
            return [];
        }
        $quantity = match ($fee['charge']) {
            'per_line'   => $this->order->book->count($chosen),
            'per_entity' => count($this->support->counted($fee)),
            default      => 1,
        };

        return $quantity > 0 ? [$this->support->draft($fee, $quantity, $amount)] : [];
    }
}
