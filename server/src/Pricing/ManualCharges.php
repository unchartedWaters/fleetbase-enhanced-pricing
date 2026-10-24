<?php

namespace Fleetbase\EnhancedPricing\Pricing;

use Fleetbase\EnhancedPricing\Profile\ProfileReferences as R;
use Fleetbase\EnhancedPricing\Support\Exact;

/**
 * Manual charges on the order, after the service fee. A new charge is priced from the profile and
 * checked against its limits; a stored charge bills the amount saved with it.
 */
final class ManualCharges
{
    public const MAX_PER_ORDER = 20;

    public function __construct(private readonly OrderContext $order)
    {
    }

    /**
     * @param list<ManualChargeInput> $charges
     */
    public function price(array $charges): void
    {
        if (count($charges) > self::MAX_PER_ORDER) {
            throw $this->order->fail('too-many-manual-charges', ['max' => self::MAX_PER_ORDER]);
        }
        $definitions = [];
        foreach (R::items($this->order->profile['manual_charges']) as $definition) {
            $definitions[R::text($definition['key'])] = $definition;
        }
        foreach ($charges as $charge) {
            $definition      = $definitions[$charge->key] ?? throw $this->order->fail('unknown-manual-charge', ['charge' => $charge->key]);
            [$amount, $name] = $charge->stored ? $this->stored($definition, $charge) : $this->priced($definition, $charge);
            $this->order->book->add([[
                'product'  => R::text($definition['product']),
                'name'     => $name,
                'quantity' => 1,
                'unit'     => $amount,
                'source'   => 'manual_charge:' . $charge->key,
            ]]);
        }
    }

    /**
     * @param array<string, mixed> $definition
     *
     * @return array{0: int, 1: string}
     */
    private function stored(array $definition, ManualChargeInput $charge): array
    {
        if ($charge->amount === null || $charge->amount < 0) {
            throw $this->order->fail('manual-charge-amount-out-of-range', ['charge' => $charge->key]);
        }
        $quantity = Exact::canonical($charge->quantity);
        $name     = R::text($definition['name']);
        if (is_array($definition['quantity']) && $quantity !== null) {
            $name = sprintf('%s (%s %s)', $name, Exact::display(Exact::of($quantity)), R::text($definition['quantity']['unit']));
        }

        return [$charge->amount, $name];
    }

    /**
     * @param array<string, mixed> $definition
     *
     * @return array{0: int, 1: string}
     */
    private function priced(array $definition, ManualChargeInput $charge): array
    {
        $key = $charge->key;
        if ($definition['offered'] !== true) {
            throw $this->order->fail('manual-charge-not-offered', ['charge' => $key]);
        }
        if ($this->order->inEffect($definition) === false) {
            throw $this->order->fail('manual-charge-not-in-effect', ['charge' => $key, 'date' => $this->order->date()]);
        }
        $name    = R::text($definition['name']);
        $entered = $definition['entered'];
        if (is_array($entered)) {
            if ($charge->quantity !== null) {
                throw $this->order->fail('manual-charge-quantity-invalid', ['charge' => $key]);
            }
            if ($charge->amount === null || $charge->amount < $entered['minimum'] || $charge->amount > $entered['maximum']) {
                throw $this->order->fail('manual-charge-amount-out-of-range', ['charge' => $key, 'minimum' => R::text($entered['minimum']), 'maximum' => R::text($entered['maximum'])]);
            }

            return [$charge->amount, $name];
        }
        if ($charge->amount !== null) {
            throw $this->order->fail('manual-charge-amount-not-entered', ['charge' => $key]);
        }
        $amount   = is_int($definition['amount']) ? $definition['amount'] : 0;
        $quantity = $definition['quantity'];
        if (is_array($quantity) === false) {
            if ($charge->quantity !== null) {
                throw $this->order->fail('manual-charge-quantity-invalid', ['charge' => $key]);
            }

            return [$amount, $name];
        }
        $value = $this->quantity($charge, $quantity);

        return [Exact::round($value->multipliedBy($amount)), sprintf('%s (%s %s)', $name, Exact::display($value), R::text($quantity['unit']))];
    }

    /**
     * A positive multiple of the step, up to the maximum.
     *
     * @param array<string, mixed> $limits
     */
    private function quantity(ManualChargeInput $charge, array $limits): \Brick\Math\BigRational
    {
        $canonical = Exact::canonical($charge->quantity);
        $invalid   = $this->order->fail('manual-charge-quantity-invalid', ['charge' => $charge->key, 'step' => R::text($limits['step']), 'maximum' => R::text($limits['maximum'])]);
        if ($canonical === null) {
            throw $invalid;
        }
        $value = Exact::of($canonical);
        if ($value->isLessThanOrEqualTo(0) || $value->isGreaterThan(R::text($limits['maximum']))
            || Exact::isWhole($value->dividedBy(R::text($limits['step']))) === false) {
            throw $invalid;
        }

        return $value;
    }
}
