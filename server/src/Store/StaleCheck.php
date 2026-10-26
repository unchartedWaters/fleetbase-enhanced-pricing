<?php

namespace Fleetbase\EnhancedPricing\Store;

use Fleetbase\EnhancedPricing\Models\Customer;
use Fleetbase\EnhancedPricing\Models\DatedChange;
use Fleetbase\EnhancedPricing\Models\PricingModel;
use Fleetbase\EnhancedPricing\Models\Profile;
use Fleetbase\EnhancedPricing\Models\RateCard;
use Fleetbase\EnhancedPricing\Models\RateCardRow;
use Fleetbase\EnhancedPricing\Profile\ChangeValidator;
use Fleetbase\EnhancedPricing\Profile\OverrideValidator;
use Fleetbase\EnhancedPricing\Profile\Problem;

/**
 * Checks every entry stored against a profile, its customers' overrides, its live dated changes
 * and its customers' rate card rows, against a new version of the profile. Each entry carries a
 * fingerprint of what was checked, so a second pass inside the profile lock rechecks only what
 * changed since the first.
 *
 * @phpstan-type Entry array{kind: string, model: PricingModel, fingerprint: string, problems: list<Problem>}
 */
final class StaleCheck
{
    /**
     * @param array<string, mixed> $definition the new version as saved
     * @param array<string, mixed> $normalized the new version normalized
     */
    public function __construct(
        private readonly string $company,
        private readonly array $definition,
        private readonly array $normalized,
    ) {
    }

    /**
     * @param array<string, Entry> $earlier entries from an earlier pass, by uuid
     *
     * @return array<string, Entry> by uuid
     */
    public function run(Profile $profile, array $earlier = []): array
    {
        $customers = Customer::listOf(Customer::ofCompany($this->company)->where('profile_uuid', $profile->uuid));
        $cards     = RateCard::ofCompany($this->company);
        $cards->getQuery()->whereIn('pricing_customer_uuid', array_map(self::uuid(...), $customers));
        $rows      = RateCardRow::ofCompany($this->company);
        $rows->getQuery()->whereIn('rate_card_uuid', array_map(self::uuid(...), RateCard::listOf($cards)));
        $changes   = DatedChange::ofCompany($this->company)->where('profile_uuid', $profile->uuid)->where('live', 1);
        $changes->getQuery()->orderBy('effective_from');
        $rows      = RateCardRow::listOf($rows);
        $changes   = DatedChange::listOf($changes);

        $entries = [];
        foreach ($customers as $customer) {
            $entries[$customer->uuid] = $this->reuse($earlier, $customer, 'customer', $this->customer(...));
        }
        foreach ($rows as $row) {
            $entries[$row->uuid] = $this->reuse($earlier, $row, 'row', $this->row(...));
        }

        return $entries + $this->changes($changes, $earlier);
    }

    /**
     * @param array<string, Entry>                  $earlier
     * @param \Closure(PricingModel): list<Problem> $check
     *
     * @return Entry
     */
    private function reuse(array $earlier, PricingModel $model, string $kind, \Closure $check): array
    {
        $fingerprint = self::fingerprint($model);
        $entry       = $earlier[$model->uuid] ?? null;
        if ($entry !== null && $entry['fingerprint'] === $fingerprint) {
            $problems = $entry['problems'];
        } else {
            $problems = $check($model);
        }

        return ['kind' => $kind, 'model' => $model, 'fingerprint' => $fingerprint, 'problems' => $problems];
    }

    /**
     * @return list<Problem>
     */
    private function customer(PricingModel $customer): array
    {
        $overrides = $customer instanceof Customer ? ($customer->overrides ?? []) : [];

        return (new OverrideValidator())->check($this->definition, $overrides);
    }

    /**
     * @return list<Problem>
     */
    private function row(PricingModel $row): array
    {
        if ($row instanceof RateCardRow === false) {
            return [];
        }
        $table = RowCheck::table($this->normalized, $row->table_key);
        if ($table === null) {
            return [StoreRefused::problem('row-unknown-table', ['table' => $row->table_key], 'table_key')];
        }
        $problems = RowCheck::check($table, $row->values ?? [], 'values');
        if ($problems === [] && RowCheck::hash(RowCheck::keyValues($table, $row->values ?? [])) !== $row->row_key) {
            $problems[] = StoreRefused::problem('row-key-changed', ['table' => $row->table_key], 'row_key');
        }

        return $problems;
    }

    /**
     * Live changes are checked together, as pricing applies them. A problem at "N...." belongs to
     * change N, and one at "" (too many changes) to every change.
     *
     * @param list<DatedChange>    $changes
     * @param array<string, Entry> $earlier
     *
     * @return array<string, Entry>
     */
    private function changes(array $changes, array $earlier): array
    {
        $fingerprints = array_map(self::fingerprint(...), $changes);
        $unchanged    = $changes !== [];
        foreach ($changes as $index => $change) {
            $unchanged = $unchanged && ($earlier[$change->uuid]['fingerprint'] ?? null) === $fingerprints[$index];
        }

        $byIndex = array_fill(0, count($changes), []);
        if ($unchanged === false && $changes !== []) {
            $list = array_map(static fn (DatedChange $change) => [
                'path'           => $change->path,
                'value'          => $change->value,
                'effective_from' => $change->effective_from,
            ], $changes);
            foreach ((new ChangeValidator())->check($this->definition, $list) as $problem) {
                $index = strstr($problem->path . '.', '.', true);
                foreach ($problem->path === '' ? array_keys($changes) : [(int) $index] as $at) {
                    $byIndex[$at][] = $problem;
                }
            }
        }

        $entries = [];
        foreach ($changes as $index => $change) {
            $entries[$change->uuid] = [
                'kind'        => 'dated_change',
                'model'       => $change,
                'fingerprint' => $fingerprints[$index],
                'problems'    => $unchanged ? $earlier[$change->uuid]['problems'] : $byIndex[$index],
            ];
        }

        return $entries;
    }

    private static function uuid(PricingModel $model): string
    {
        return $model->uuid;
    }

    private static function fingerprint(PricingModel $model): string
    {
        $fields = match (true) {
            $model instanceof Customer    => [$model->profile_uuid, $model->overrides],
            $model instanceof RateCardRow => [$model->table_key, $model->row_key, $model->values],
            $model instanceof DatedChange => [$model->path, $model->value, $model->effective_from, $model->live],
            default                       => [],
        };

        return hash('sha256', (string) json_encode($fields));
    }
}
