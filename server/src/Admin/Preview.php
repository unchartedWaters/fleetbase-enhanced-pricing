<?php

namespace Fleetbase\EnhancedPricing\Admin;

use Fleetbase\EnhancedPricing\Models\Customer;
use Fleetbase\EnhancedPricing\Models\Profile;
use Fleetbase\EnhancedPricing\Models\RateCard;
use Fleetbase\EnhancedPricing\Models\RateCardRow;
use Fleetbase\EnhancedPricing\Pricing\PricingEngine;
use Fleetbase\EnhancedPricing\Pricing\PricingFailed;
use Fleetbase\EnhancedPricing\Pricing\PricingRequest;
use Fleetbase\EnhancedPricing\Profile\ProfileReferences as R;
use Fleetbase\EnhancedPricing\Store\StoreContext;

/**
 * The admin page's quote preview: a sample order priced by the real engine from what is stored for
 * a customer, or from the profile's global values, saving nothing.
 *
 * It gets what pricing will get: the current version, the customer's overrides, and the live
 * dated changes that still fit. A stale dated change is left out and listed (decision 10.5). A
 * customer with stale overrides gets its problems instead of a quote (decision 10.4). A row must
 * be the customer's, of the service rate's table, and without problems (decision 11).
 */
final class Preview
{
    private readonly StoreContext $context;

    private readonly Names $names;

    /**
     * @param (callable(): \DateTimeImmutable)|null $clock
     */
    public function __construct(private readonly string $company, ?callable $clock = null)
    {
        $this->context = new StoreContext($company, $clock);
        $this->names   = new Names($company);
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array{
     *     quote: array<string, mixed>|null,
     *     failed: array<string, mixed>|null,
     *     stale: list<array<string, mixed>>|null,
     *     skipped_changes: list<array{path: string, effective_from: string}>
     * }
     */
    public function price(array $input): array
    {
        $order    = new PreviewInput($input);
        $customer = $this->customer($input['customer'] ?? null);
        $profile  = $customer === null ? $this->profile($input['profile'] ?? null) : $customer->profile();
        $state    = new ProfileState($this->context, $profile ?? throw AdminRefused::notFound());
        $changes  = [];
        $skipped  = [];
        foreach ($state->live as $change) {
            if ($change->problems === null) {
                $changes[] = [
                    'path'           => $change->path,
                    'value'          => $change->value,
                    'effective_from' => $change->effective_from,
                ];
            } else {
                $skipped[] = ['path' => $change->path, 'effective_from' => $change->effective_from];
            }
        }
        $result = ['quote' => null, 'failed' => null, 'stale' => null, 'skipped_changes' => $skipped];
        if ($customer?->problems !== null) {
            return ['stale' => $customer->problems] + $result;
        }
        $serviceRate    = $order->serviceRate();
        [$row, $labels] = $this->row($order->row(), $customer, $state, $serviceRate);
        $timezone       = $order->timezone(R::text($state->normalized['scheduling_timezone']));
        $request        = new PricingRequest(
            profile: $state->definition,
            serviceRate: $serviceRate,
            pickupAt: $order->pickupAt($timezone),
            timezone: $timezone,
            entities: $order->entities(),
            attributes: $order->attributes(),
            row: $row,
            distanceMeters: $order->distanceMeters(),
            manualCharges: $order->manualCharges(),
            overrides: $customer->overrides ?? [],
            datedChanges: $changes,
            labels: $labels,
        );
        try {
            return ['quote' => (new PricingEngine())->price($request)->toArray()] + $result;
        } catch (PricingFailed $failed) {
            return ['failed' => ['key' => $failed->key, 'params' => $failed->params]] + $result;
        }
    }

    private function customer(mixed $uuid): ?Customer
    {
        if ($uuid === null || $uuid === '') {
            return null;
        }

        if (!is_string($uuid)) {
            throw AdminRefused::invalid('field-invalid', [], 'customer');
        }

        return Customer::firstOf(Customer::ofCompany($this->company)->whereKey($uuid))
            ?? throw AdminRefused::notFound();
    }

    private function profile(mixed $key): Profile
    {
        if (is_string($key) === false || $key === '') {
            throw AdminRefused::invalid('field-required', [], 'profile');
        }

        return Profile::firstOf(Profile::ofCompany($this->company)->where('key', $key))
            ?? throw AdminRefused::notFound();
    }

    /**
     * The chosen row's values, and the labels messages name the customer, card and row by.
     *
     * @return array{array<string, mixed>|null, array<string, string>}
     */
    private function row(?string $uuid, ?Customer $customer, ProfileState $state, string $serviceRate): array
    {
        $labels = $customer === null ? [] : [
            'customer' => $this->names->customers([$customer->customer_uuid])[$customer->customer_uuid] ?? '',
        ];
        if ($uuid === null) {
            return [null, $labels];
        }
        $row   = RateCardRow::firstOf(RateCardRow::ofCompany($this->company)->whereKey($uuid));
        $card  = $row === null
            ? null
            : RateCard::firstOf(RateCard::ofCompany($this->company)->whereKey($row->rate_card_uuid));
        $table = null;
        foreach (R::items($state->normalized['service_rates']) as $rate) {
            if ($rate['key'] === $serviceRate) {
                $table = $rate['rate_table'];
            }
        }
        $fits = $row !== null && $card !== null && $customer !== null
            && $card->pricing_customer_uuid === $customer->uuid
            && $row->table_key === $table
            && $row->problems === null;
        if ($fits === false) {
            throw AdminRefused::invalid('row-not-usable', [], 'row');
        }
        /** @var RateCard $card */
        /** @var RateCardRow $row */
        $key = implode(' ', array_map(
            static fn (mixed $value): string => is_scalar($value) ? (string) $value : '',
            $row->key_values ?? [],
        ));

        $values = [];
        foreach ($row->values ?? [] as $column => $value) {
            $values[(string) $column] = $value;
        }

        return [$values, $labels + ['card' => $card->label, 'row' => $key]];
    }
}
