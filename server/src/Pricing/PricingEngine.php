<?php

namespace Fleetbase\EnhancedPricing\Pricing;

use Fleetbase\EnhancedPricing\Profile\ProfileReferences as R;

/**
 * Prices one order from its customer's profile, overrides, the dated changes in effect and the
 * chosen rate card row. It reads nothing from the database and saves nothing, so the preview and
 * the pricing job get the same lines from the same request. docs/pricing-engine.md describes it.
 */
final class PricingEngine
{
    /**
     * @throws PricingFailed
     */
    public function price(PricingRequest $request): Quote
    {
        $resolved = (new RequestResolver())->resolve($request);
        $rate     = $this->serviceRate($resolved['profile'], $resolved['allowed'], $request);
        $order    = new OrderContext($resolved['profile'], $rate, $resolved['local'], $request->labels, $request->row);
        $this->checkRow($order, $request);
        $order->readInputs($request->attributes, $request->entities);

        (new ServiceFee($order, $request->distanceMeters))->price();
        (new ManualCharges($order))->price($request->manualCharges);
        (new Fees($order))->price();
        (new SubtotalFees($order))->price();

        return new Quote(
            R::text($order->profile['currency']),
            $order->book->lines(),
            $order->notes,
            [
                'timezone'     => $request->timezone,
                'pricing_date' => $order->date(),
                'service_rate' => $request->serviceRate,
                'overrides'    => $request->overrides,
                'changes'      => $resolved['changes'],
            ],
        );
    }

    /**
     * @param array<string, mixed> $profile the resolved profile
     * @param list<string>|null    $allowed the customer's allowed service rates, or null for all
     *
     * @return array<string, mixed>
     */
    private function serviceRate(array $profile, ?array $allowed, PricingRequest $request): array
    {
        $key = $request->serviceRate;
        foreach (R::items($profile['service_rates']) as $rate) {
            if ($rate['key'] !== $key) {
                continue;
            }
            $name = R::text($rate['name']);
            if ($rate['enabled'] !== true) {
                throw PricingFailed::because('service-rate-disabled', ['service_rate' => $name]);
            }
            if ($allowed !== null && in_array($key, $allowed, true) === false) {
                throw PricingFailed::because('service-rate-not-allowed', ['service_rate' => $name, 'customer'     => $request->labels['customer'] ?? '']);
            }

            return $rate;
        }

        throw PricingFailed::because('unknown-service-rate', ['service_rate' => $key]);
    }

    private function checkRow(OrderContext $order, PricingRequest $request): void
    {
        $table = $order->serviceRate['rate_table'];
        if (is_string($table) === false) {
            return;
        }
        if ($request->row === null) {
            throw $order->fail('row-required', ['service_rate' => R::text($order->serviceRate['name'])]);
        }
        foreach (R::items($order->profile['rate_tables']) as $definition) {
            if ($definition['key'] === $table) {
                ServiceFee::checkRow($order, $definition, $request->row);
            }
        }
    }
}
