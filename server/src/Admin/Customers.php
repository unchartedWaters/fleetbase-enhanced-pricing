<?php

namespace Fleetbase\EnhancedPricing\Admin;

use Fleetbase\EnhancedPricing\Models\Customer;
use Fleetbase\EnhancedPricing\Models\Profile;
use Fleetbase\EnhancedPricing\Models\RateCard;
use Fleetbase\EnhancedPricing\Models\RateCardRow;
use Fleetbase\EnhancedPricing\Store\StoreContext;

/**
 * The admin page's customers of a profile, and one customer's overrides next to the global values,
 * its stale marks, and its cards and rows. Read-only: overrides and cards change through a loader
 * (plan, Console changes in the order form).
 */
final class Customers
{
    public const PER_PAGE = 50;

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
     * @return array{customers: list<array<string, mixed>>, total: int, page: int, per_page: int}
     */
    public function list(string $profileKey, int $page = 1, int $perPage = self::PER_PAGE): array
    {
        $profile = Profile::firstOf(Profile::ofCompany($this->company)->where('key', $profileKey))
            ?? throw AdminRefused::notFound();
        $page    = max(1, $page);
        $perPage = max(1, min(self::PER_PAGE, $perPage));
        $query   = Customer::ofCompany($this->company)->where('profile_uuid', $profile->uuid);
        $total   = (clone $query)->count();
        // Sorted by the Fleet-Ops name, which lives in another table: read the names of all of the
        // profile's customers (uuids only), sort, then load one page.
        $uuids   = [];
        foreach ((clone $query)->getQuery()->get(['uuid', 'customer_uuid']) as $row) {
            if (is_object($row) && is_string($row->uuid ?? null) && is_string($row->customer_uuid ?? null)) {
                $uuids[$row->uuid] = $row->customer_uuid;
            }
        }
        $names   = $this->names->customers(array_values($uuids));
        $order   = [];
        foreach ($uuids as $uuid => $contact) {
            $order[$uuid] = mb_strtolower($names[$contact] ?? '') . "\0" . $uuid;
        }
        asort($order);
        $pageUuids = array_slice(array_keys($order), ($page - 1) * $perPage, $perPage);
        $customers = Customer::listOf(Customer::ofCompany($this->company)->whereKey($pageUuids));
        usort($customers, static fn (Customer $left, Customer $right): int => $order[$left->uuid] <=> $order[$right->uuid]);
        $cardQuery = RateCard::ofCompany($this->company);
        $cardQuery->getQuery()->whereIn('pricing_customer_uuid', $pageUuids);
        $cards     = Counts::by($cardQuery, 'pricing_customer_uuid');

        return [
            'customers' => array_map(fn (Customer $customer): array => [
                'uuid'            => $customer->uuid,
                'customer_uuid'   => $customer->customer_uuid,
                'name'            => $names[$customer->customer_uuid] ?? '',
                'overrides'       => count($customer->overrides ?? []),
                'cards'           => $cards[$customer->uuid] ?? 0,
                'checked_version' => $customer->checked_version,
                'problems'        => $customer->problems,
            ], $customers),
            'total'     => $total,
            'page'      => $page,
            'per_page'  => $perPage,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function show(string $uuid): array
    {
        $customer = Customer::firstOf(Customer::ofCompany($this->company)->whereKey($uuid))
            ?? throw AdminRefused::notFound();
        $profile  = $customer->profile() ?? throw AdminRefused::notFound();
        $state    = new ProfileState($this->context, $profile);
        $paths    = array_map(
            static fn (int|string $path): string => (string) $path,
            array_keys($customer->overrides ?? []),
        );
        sort($paths);

        return [
            'uuid'            => $customer->uuid,
            'customer_uuid'   => $customer->customer_uuid,
            'name'            => $this->names->customers([$customer->customer_uuid])[$customer->customer_uuid] ?? '',
            'profile'         => [
                'key'             => $profile->key,
                'name'            => $profile->name,
                'currency'        => $profile->currency,
                'current_version' => $profile->current_version,
                'columns'         => self::columns($state->normalized),
            ],
            'checked_version' => $customer->checked_version,
            'problems'        => $customer->problems,
            'overrides'       => array_map(static fn (string $path): array => [
                'path'    => $path,
                'value'   => ($customer->overrides ?? [])[$path] ?? null,
                'default' => $state->globalValue($path),
                'change'  => $state->changeInEffect($path),
            ], $paths),
            'cards'           => $this->cards($customer),
        ];
    }

    /**
     * Each rate table's column types, so the console shows money columns in the profile's
     * currency.
     *
     * @param array<string, mixed> $normalized
     *
     * @return array<string, array<string, string>>
     */
    private static function columns(array $normalized): array
    {
        $tables = [];
        foreach (is_array($normalized['rate_tables'] ?? null) ? $normalized['rate_tables'] : [] as $table) {
            if (is_array($table) === false || is_string($table['key'] ?? null) === false) {
                continue;
            }
            $columns = [];
            foreach (is_array($table['columns'] ?? null) ? $table['columns'] : [] as $column) {
                if (is_array($column) && is_string($column['key'] ?? null) && is_string($column['type'] ?? null)) {
                    $columns[$column['key']] = $column['type'];
                }
            }
            $tables[$table['key']] = $columns;
        }

        return $tables;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function cards(Customer $customer): array
    {
        $query = RateCard::ofCompany($this->company)->where('pricing_customer_uuid', $customer->uuid);
        $query->getQuery()->orderBy('label');
        $cards     = RateCard::listOf($query);
        $rows      = [];
        $query     = RateCardRow::ofCompany($this->company);
        $cardUuids = array_map(static fn (RateCard $card): string => $card->uuid, $cards);
        $query->getQuery()->whereIn('rate_card_uuid', $cardUuids);
        foreach (RateCardRow::listOf($query) as $row) {
            $rows[$row->rate_card_uuid][] = $row;
        }

        return array_map(static function (RateCard $card) use ($rows): array {
            $own = $rows[$card->uuid] ?? [];
            usort($own, static fn (RateCardRow $left, RateCardRow $right): int => [
                $left->table_key,
                json_encode($left->key_values),
            ] <=> [$right->table_key, json_encode($right->key_values)]);

            return [
                'uuid'  => $card->uuid,
                'label' => $card->label,
                'rows'  => array_map(static fn (RateCardRow $row): array => [
                    'uuid'            => $row->uuid,
                    'table_key'       => $row->table_key,
                    'key_values'      => $row->key_values,
                    'values'          => $row->values,
                    'checked_version' => $row->checked_version,
                    'problems'        => $row->problems,
                ], $own),
            ];
        }, $cards);
    }
}
