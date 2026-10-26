<?php

namespace Fleetbase\EnhancedPricing\Admin;

use Fleetbase\EnhancedPricing\Models\Customer;
use Fleetbase\EnhancedPricing\Models\DatedChange;
use Fleetbase\EnhancedPricing\Models\RateCard;
use Fleetbase\EnhancedPricing\Models\RateCardRow;

/**
 * A readable name for each stale entry a new version would leave: the store lists only kinds and
 * uuids. A customer is its Fleet-Ops name; a dated change, its path and date; a row, its
 * customer, card label and key values.
 */
final class StaleLabels
{
    public function __construct(private readonly string $company, private readonly Names $names)
    {
    }

    /**
     * @param list<array{kind: string, uuid: string, ...}> $stale
     *
     * @return array<string, string> by uuid
     */
    public function for(array $stale): array
    {
        $uuids = [];
        foreach ($stale as $entry) {
            $uuids[$entry['kind']][] = $entry['uuid'];
        }
        $labels  = [];
        $changes = DatedChange::listOf(DatedChange::ofCompany($this->company)->whereKey($uuids['dated_change'] ?? []));
        foreach ($changes as $change) {
            $labels[$change->uuid] = $change->path . ' · ' . $change->effective_from;
        }
        $rows      = RateCardRow::listOf(RateCardRow::ofCompany($this->company)->whereKey($uuids['row'] ?? []));
        $cards     = RateCard::listOf(RateCard::ofCompany($this->company)->whereKey(
            array_map(static fn (RateCardRow $row): string => $row->rate_card_uuid, $rows),
        ));
        $customers = Customer::listOf(Customer::ofCompany($this->company)->whereKey(array_merge(
            $uuids['customer'] ?? [],
            array_map(static fn (RateCard $card): string => $card->pricing_customer_uuid, $cards),
        )));
        $names     = $this->names->customers(
            array_map(static fn (Customer $customer) => $customer->customer_uuid, $customers),
        );
        $byUuid    = [];
        foreach ($customers as $customer) {
            $byUuid[$customer->uuid] = $names[$customer->customer_uuid] ?? '';
            $labels[$customer->uuid] = $byUuid[$customer->uuid];
        }
        $cardLabels = [];
        foreach ($cards as $card) {
            $cardLabels[$card->uuid] = ($byUuid[$card->pricing_customer_uuid] ?? '') . ' · ' . $card->label;
        }
        foreach ($rows as $row) {
            $key                = implode(' ', array_map(
                static fn (mixed $value): string => is_scalar($value) ? (string) $value : '',
                $row->key_values ?? [],
            ));
            $labels[$row->uuid] = ($cardLabels[$row->rate_card_uuid] ?? '') . ' · ' . $key;
        }

        return $labels;
    }
}
