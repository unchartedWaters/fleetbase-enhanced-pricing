<?php

namespace Fleetbase\EnhancedPricing\Store;

use Fleetbase\EnhancedPricing\Models\CrossCompanyReference;
use Fleetbase\EnhancedPricing\Models\Customer;
use Fleetbase\EnhancedPricing\Models\Profile;
use Fleetbase\EnhancedPricing\Models\RateCard;
use Fleetbase\EnhancedPricing\Models\RateCardRow;
use Fleetbase\EnhancedPricing\Profile\Problem;
use Illuminate\Support\Str;

/**
 * Customers, their overrides, rate cards and rows, for PricingStore. A customer's overrides are
 * checked by the Customer model when they or its profile change; rows are checked here, every row
 * before any is written.
 */
final class RateStore
{
    public const MAX_ROWS_PER_CARD = 2000;

    public const MAX_ROWS_PER_CALL = 5000;

    private const INSERT_CHUNK = 200;

    public function __construct(private readonly StoreContext $context)
    {
    }

    /**
     * @param array<string, mixed>|null $overrides
     * @param array<mixed>|null         $meta
     *
     * @throws StoreRefused
     */
    public function saveCustomer(
        string $customerUuid,
        Profile $profile,
        ?array $overrides,
        ?array $meta,
        bool $acceptLargeChange = false,
    ): Customer {
        $this->context->own($profile);
        $this->context->checkMeta($meta);
        if ($this->isCustomer($customerUuid) === false) {
            throw StoreRefused::refuse('not-a-customer', [], 'customer_uuid');
        }

        $save = function () use ($customerUuid, $profile, $overrides, $meta, $acceptLargeChange): Customer {
            $this->context->lock($profile);
            $customer = Customer::firstOf(
                Customer::ofCompany($this->context->company)->where('customer_uuid', $customerUuid),
            ) ?? new Customer(['company_uuid' => $this->context->company, 'customer_uuid' => $customerUuid]);
            if ($customer->exists && $customer->profile_uuid !== $profile->uuid && $customer->hasCards()) {
                throw StoreRefused::refuse('customer-has-cards', [], 'profile_uuid');
            }
            $stored                 = $customer->profile_uuid === $profile->uuid ? $customer->overrides ?? [] : [];
            $customer->profile_uuid = $profile->uuid;
            if ($overrides !== null) {
                $customer->overrides = $overrides;
                $customer->checkOverrides();
            }
            if ($overrides !== null && $acceptLargeChange === false) {
                SlipCheck::run($this->context->current($profile)['normalized'], $overrides, $stored);
            }
            if ($meta !== null) {
                $customer->meta = $meta;
            }
            $customer->save();

            return $customer;
        };

        return $this->context->transaction($save);
    }

    /**
     * The profile's customers with an override at a path, which a dated change of that path
     * won't reach.
     *
     * @return list<Customer>
     */
    public function withOverride(Profile $profile, string $path): array
    {
        $this->context->own($profile);
        $customers = Customer::listOf(
            Customer::ofCompany($this->context->company)->where('profile_uuid', $profile->uuid),
        );

        return array_values(array_filter(
            $customers,
            static fn (Customer $customer): bool => array_key_exists($path, $customer->overrides ?? []),
        ));
    }

    /**
     * @param array<mixed>|null $meta
     *
     * @throws StoreRefused
     */
    public function saveCard(Customer $customer, string $label, ?array $meta): RateCard
    {
        $this->context->own($customer);
        $this->context->checkMeta($meta);

        $card = RateCard::firstOf(
            RateCard::ofCompany($this->context->company)
                ->where('pricing_customer_uuid', $customer->uuid)->where('label', $label),
        ) ?? new RateCard([
            'company_uuid'          => $this->context->company,
            'pricing_customer_uuid' => $customer->uuid,
            'label'                 => $label,
        ]);
        if ($meta !== null) {
            $card->meta = $meta;
        }
        $card->save();

        return $card;
    }

    /**
     * Upserts rows of one rate table on a card, on their keys. Every row is checked against the
     * current version first; one bad row refuses the whole call and nothing is written. An existing
     * row keeps its uuid and key.
     *
     * @param list<array{values: array<mixed>, meta?: array<mixed>|null}> $rows
     *
     * @return list<RateCardRow>
     *
     * @throws StoreRefused
     */
    public function saveRows(RateCard $card, string $tableKey, array $rows): array
    {
        $this->context->own($card);
        if (count($rows) > self::MAX_ROWS_PER_CALL) {
            throw StoreRefused::refuse('too-many-rows', ['max' => self::MAX_ROWS_PER_CALL]);
        }
        // Rows are inserted without the model's check, so the card is read back as the company's.
        $card     = RateCard::firstOf(RateCard::ofCompany($this->context->company)->whereKey($card->uuid))
            ?? throw new CrossCompanyReference(RateCard::class, 'uuid');
        $customer = $card->customer();
        $profile  = $customer?->profile();
        if ($customer === null || $profile === null) {
            throw new CrossCompanyReference(RateCard::class, 'pricing_customer_uuid');
        }

        return $this->context->transaction(function () use ($card, $tableKey, $rows, $profile): array {
            $current = $this->context->current($this->context->lock($profile));
            $table   = RowCheck::table($current['normalized'], $tableKey);
            if ($table === null) {
                throw StoreRefused::refuse('row-unknown-table', ['table' => $tableKey], 'table_key');
            }
            $checked  = $this->check($table, $rows);
            $existing = $this->existing($card, $tableKey);
            $total    = RateCardRow::ofCompany($this->context->company)->where('rate_card_uuid', $card->uuid)->count()
                + count(array_diff_key($checked, $existing));
            if ($total > self::MAX_ROWS_PER_CARD) {
                throw StoreRefused::refuse('too-many-rows-on-card', ['max' => self::MAX_ROWS_PER_CARD]);
            }

            $this->write($card, $tableKey, $checked, $existing, $current['version']);
            $saved = $this->existing($card, $tableKey);

            return array_map(static fn (string $key) => $saved[$key], array_keys($checked));
        });
    }

    /**
     * The card's rows of one table, by row key.
     *
     * @return array<string, RateCardRow>
     */
    private function existing(RateCard $card, string $tableKey): array
    {
        $query = RateCardRow::ofCompany($this->context->company)
            ->where('rate_card_uuid', $card->uuid)
            ->where('table_key', $tableKey);
        $rows = [];
        foreach (RateCardRow::listOf($query) as $row) {
            $rows[$row->row_key] = $row;
        }

        return $rows;
    }

    /**
     * Writes checked rows. New rows are inserted together, in chunks; the card was already checked
     * as the company's, so the model's per-row check isn't needed. An existing row is saved only
     * when something changed, and keeps its uuid, key and key values.
     *
     * @param array<string, array{key: list<mixed>, values: array<mixed>, meta?: array<mixed>|null}> $checked
     * @param array<string, RateCardRow>                                                             $existing
     */
    private function write(RateCard $card, string $tableKey, array $checked, array $existing, int $version): void
    {
        $now    = (new RateCardRow())->freshTimestampString();
        $insert = [];
        foreach ($checked as $key => $row) {
            $model = $existing[$key] ?? null;
            if ($model === null) {
                $insert[] = [
                    'uuid'            => (string) Str::uuid(),
                    'company_uuid'    => $this->context->company,
                    'rate_card_uuid'  => $card->uuid,
                    'table_key'       => $tableKey,
                    'row_key'         => $key,
                    'key_values'      => json_encode($row['key']),
                    'values'          => json_encode($row['values']),
                    'checked_version' => $version,
                    'problems'        => null,
                    'meta'            => isset($row['meta']) ? json_encode($row['meta']) : null,
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ];
                continue;
            }
            if ($this->unchanged($model, $row, $version)) {
                continue;
            }
            $model->values          = $row['values'];
            $model->checked_version = $version;
            $model->problems        = null;
            if (array_key_exists('meta', $row)) {
                $model->meta = $row['meta'];
            }
            $model->save();
        }
        foreach (array_chunk($insert, self::INSERT_CHUNK) as $chunk) {
            RateCardRow::query()->insert($chunk);
        }
    }

    /**
     * Whether a stored row already holds what was sent, checked against this version. Values are
     * compared canonically, since MySQL hands JSON objects back with their keys reordered.
     *
     * @param array{key: list<mixed>, values: array<mixed>, meta?: array<mixed>|null} $row
     */
    private function unchanged(RateCardRow $model, array $row, int $version): bool
    {
        $meta = array_key_exists('meta', $row) === false
            || Canonical::json($model->meta) === Canonical::json($row['meta']);

        return $meta
            && $model->checked_version === $version
            && $model->problems === null
            && Canonical::json($model->values) === Canonical::json($row['values']);
    }

    /**
     * @param array<string, mixed>                                        $table
     * @param list<array{values: array<mixed>, meta?: array<mixed>|null}> $rows
     *
     * @return array<string, array{key: list<mixed>, values: array<mixed>, meta?: array<mixed>|null}> by row key
     *
     * @throws StoreRefused
     */
    private function check(array $table, array $rows): array
    {
        /** @var list<Problem> $problems */
        $problems = [];
        $checked  = [];
        foreach ($rows as $index => $row) {
            $found = RowCheck::check($table, $row['values'], "$index.values");
            if (array_key_exists('meta', $row)) {
                $found = [...$found, ...$this->metaProblems($row['meta'], "$index.meta")];
            }
            if ($found !== []) {
                $problems = [...$problems, ...$found];
                continue;
            }
            $keyValues = RowCheck::keyValues($table, $row['values']);
            $hash      = RowCheck::hash($keyValues);
            if (isset($checked[$hash])) {
                $problems[] = StoreRefused::problem('duplicate-row', [], "$index.values");
                continue;
            }
            $checked[$hash] = ['key' => $keyValues] + $row;
        }
        if ($problems !== []) {
            throw new StoreRefused($problems);
        }

        return $checked;
    }

    /**
     * @param array<mixed>|null $meta
     *
     * @return list<Problem>
     */
    private function metaProblems(?array $meta, string $path): array
    {
        try {
            $this->context->checkMeta($meta, $path);
        } catch (StoreRefused $refused) {
            return $refused->problems;
        }

        return [];
    }

    /**
     * A Fleet-Ops contact of type customer, of this company and not deleted. Read from the table,
     * since the extension doesn't depend on Fleet-Ops' models.
     */
    private function isCustomer(string $uuid): bool
    {
        return $this->context->connection()->table('contacts')
            ->where('uuid', $uuid)
            ->where('company_uuid', $this->context->company)
            ->where('type', 'customer')
            ->whereNull('deleted_at')
            ->exists();
    }
}
