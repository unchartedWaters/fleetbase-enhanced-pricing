<?php

namespace Fleetbase\EnhancedPricing\Pricing;

use Fleetbase\EnhancedPricing\Profile\ChangeValidator;
use Fleetbase\EnhancedPricing\Profile\InvalidProfile;
use Fleetbase\EnhancedPricing\Profile\OverrideValidator;
use Fleetbase\EnhancedPricing\Profile\ProfileReferences as R;
use Fleetbase\EnhancedPricing\Profile\ProfileResolver;
use Fleetbase\EnhancedPricing\Profile\ProfileValidator;

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
        [$profile, $local] = $this->checkRequest($request);
        $resolved          = (new ProfileResolver())->resolve($profile, $request->overrides, $request->valueChanges, $local->format('Y-m-d'));
        $problems          = (new ProfileValidator())->recheck($resolved['profile']);
        if ($problems !== []) {
            throw PricingFailed::because('profile-invalid-for-order', ['path' => $problems[0]->path]);
        }
        $rate  = $this->serviceRate($resolved['profile'], $resolved['allowed'], $request->serviceRate);
        $order = new OrderContext($resolved['profile'], $rate, $local, $request->labels, $request->row);
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
     * Checks the profile, overrides, changes and timezone, and returns the normalized profile and
     * the pickup's local time.
     *
     * @return array{array<string, mixed>, \DateTimeImmutable}
     */
    private function checkRequest(PricingRequest $request): array
    {
        try {
            $profile = (new ProfileValidator())->normalize($request->profile);
        } catch (InvalidProfile $invalid) {
            throw PricingFailed::because('profile-invalid', ['problems' => count($invalid->problems)]);
        }
        if ((new OverrideValidator())->check($request->profile, $request->overrides) !== []) {
            throw PricingFailed::because('overrides-invalid');
        }
        if ((new ChangeValidator())->checkEach($request->profile, $request->valueChanges) !== []) {
            throw PricingFailed::because('changes-invalid');
        }
        try {
            return [$profile, $request->pickupAt->setTimezone(new \DateTimeZone($request->timezone))];
        } catch (\Exception) {
            throw PricingFailed::because('timezone-invalid', ['timezone' => $request->timezone]);
        }
    }

    /**
     * @param array<string, mixed> $profile the resolved profile
     * @param list<string>|null    $allowed the customer's allowed service rates, or null for all
     *
     * @return array<string, mixed>
     */
    private function serviceRate(array $profile, ?array $allowed, string $key): array
    {
        foreach (R::items($profile['service_rates']) as $rate) {
            if ($rate['key'] !== $key) {
                continue;
            }
            if ($rate['enabled'] !== true || ($allowed !== null && in_array($key, $allowed, true) === false)) {
                throw PricingFailed::because('service-rate-not-allowed', ['service_rate' => $key]);
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
            throw $order->fail('row-required', ['service_rate' => $request->serviceRate]);
        }
        foreach (R::items($order->profile['rate_tables']) as $definition) {
            if ($definition['key'] === $table) {
                ServiceFee::checkRow($order, $definition, $request->row);
            }
        }
    }
}
