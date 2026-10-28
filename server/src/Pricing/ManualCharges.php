<?php

namespace Fleetbase\EnhancedPricing\Pricing;

use Fleetbase\EnhancedPricing\Profile\ProfileReferences as R;
use Fleetbase\EnhancedPricing\Support\Exact;
use Fleetbase\EnhancedPricing\Support\MinorUnits;

/**
 * Manual charges on the order, after the service fee. A new charge is priced from the profile and
 * checked against its limits; a charge already added bills the product, name and amount stored
 * with it. Either way the profile must still have the charge's key.
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
            $definition = $definitions[$charge->key]
                ?? throw $this->order->fail('unknown-manual-charge', ['charge' => $charge->key]);
            $line       = $charge->isAdded() ? $this->added($charge) : $this->priced($definition, $charge);
            $this->order->book->add([[
                ...$line,
                'quantity' => 1,
                'source'   => 'manual_charge:' . $charge->key,
            ]]);
        }
    }

    /**
     * A charge already added bills what was stored with it.
     *
     * @return array{product: string, name: string, unit: int}
     */
    private function added(ManualChargeInput $charge): array
    {
        $name    = R::text($charge->name);
        $product = R::text($charge->product);
        if ($charge->amount === null || $charge->amount < 0) {
            throw $this->order->fail('manual-charge-amount-invalid', ['charge' => $name]);
        }
        $products = array_map(
            static fn (array $known): mixed => $known['code'],
            R::items($this->order->profile['products']),
        );
        if (in_array($product, $products, true) === false) {
            throw $this->order->fail('manual-charge-product-unknown', ['charge' => $name, 'product' => $product]);
        }

        return ['product' => $product, 'name' => $name, 'unit' => $charge->amount];
    }

    /**
     * @param array<string, mixed> $definition
     *
     * @return array{product: string, name: string, unit: int}
     */
    private function priced(array $definition, ManualChargeInput $charge): array
    {
        $name = R::text($definition['name']);
        $line = ['product' => R::text($definition['product']), 'name' => $name];
        if ($definition['offered'] !== true) {
            throw $this->order->fail('manual-charge-not-offered', ['charge' => $name]);
        }
        if ($this->order->inEffect($definition) === false) {
            $params = ['charge' => $name, 'date' => $this->order->date()];
            throw $this->order->fail('manual-charge-not-in-effect', $params);
        }
        $entered = $definition['entered'];
        if (is_array($entered)) {
            return $line + ['unit' => $this->entered(R::map($entered), $charge, $name)];
        }
        if ($charge->amount !== null) {
            throw $this->order->fail('manual-charge-amount-not-entered', ['charge' => $name]);
        }
        $amount   = is_int($definition['amount']) ? $definition['amount'] : 0;
        $quantity = $definition['quantity'];
        if (is_array($quantity) === false) {
            if ($charge->quantity !== null) {
                throw $this->order->fail('manual-charge-takes-no-quantity', ['charge' => $name]);
            }

            return $line + ['unit' => $amount];
        }
        $value = $this->quantity($charge, R::map($quantity), $name);

        return [
            'product' => $line['product'],
            'name'    => sprintf('%s (%s %s)', $name, Exact::display($value), R::text($quantity['unit'])),
            'unit'    => Exact::round($value->multipliedBy($amount)),
        ];
    }

    /**
     * The amount entered for a charge whose amount is entered, checked against its limits.
     *
     * @param array<string, mixed> $entered
     */
    private function entered(array $entered, ManualChargeInput $charge, string $name): int
    {
        if ($charge->quantity !== null) {
            throw $this->order->fail('manual-charge-takes-no-quantity', ['charge' => $name]);
        }
        if ($charge->amount === null) {
            throw $this->order->fail('manual-charge-amount-required', ['charge' => $name]);
        }
        $minimum = is_int($entered['minimum']) ? $entered['minimum'] : 0;
        $maximum = is_int($entered['maximum']) ? $entered['maximum'] : 0;
        if ($charge->amount < $minimum || $charge->amount > $maximum) {
            $currency   = R::text($this->order->profile['currency']);
            $outOfRange = $this->order->fail('manual-charge-amount-out-of-range', [
                'charge'   => $name,
                'minimum'  => MinorUnits::decimal($minimum, $currency),
                'maximum'  => MinorUnits::decimal($maximum, $currency),
                'currency' => $currency,
            ]);
            throw $outOfRange;
        }

        return $charge->amount;
    }

    /**
     * A positive multiple of the step, up to the maximum.
     *
     * @param array<string, mixed> $limits
     */
    private function quantity(ManualChargeInput $charge, array $limits, string $name): \Brick\Math\BigRational
    {
        $canonical = Exact::canonical($charge->quantity);
        $invalid   = $this->order->fail('manual-charge-quantity-invalid', [
            'charge'  => $name,
            'step'    => R::text($limits['step']),
            'maximum' => R::text($limits['maximum']),
        ]);
        if ($canonical === null) {
            throw $invalid;
        }
        $value = Exact::rational($canonical);
        if (
            $value->isLessThanOrEqualTo(0)
            || $value->isGreaterThan(R::text($limits['maximum']))
            || Exact::isWhole($value->dividedBy(R::text($limits['step']))) === false
        ) {
            throw $invalid;
        }

        return $value;
    }
}
