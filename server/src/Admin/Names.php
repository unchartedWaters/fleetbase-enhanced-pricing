<?php

namespace Fleetbase\EnhancedPricing\Admin;

use Fleetbase\EnhancedPricing\Models\Profile;

/**
 * Names for the uuids the pricing tables store: Fleet-Ops customers (contacts) and the core users
 * who saved a version or a change. Both are read as plain tables, so the extension doesn't depend
 * on Fleet-Ops. Customers are looked up within the company. Users are looked up by uuid alone:
 * the uuid comes from the company's own rows, and a user's company_uuid is only the company it is
 * using now.
 */
final class Names
{
    public function __construct(private readonly string $company)
    {
    }

    /**
     * @param list<string|null> $uuids
     *
     * @return array<string, string>
     */
    public function customers(array $uuids): array
    {
        return $this->names('contacts', $uuids, true);
    }

    /**
     * @param list<string|null> $uuids
     *
     * @return array<string, string>
     */
    public function users(array $uuids): array
    {
        return $this->names('users', $uuids, false);
    }

    /**
     * @param array<string, string> $names
     *
     * @return array{uuid: string, name: string}|null
     */
    public static function person(?string $uuid, array $names): ?array
    {
        return $uuid === null ? null : ['uuid' => $uuid, 'name' => $names[$uuid] ?? ''];
    }

    /**
     * @param list<string|null> $uuids
     *
     * @return array<string, string>
     */
    private function names(string $table, array $uuids, bool $ofCompany): array
    {
        $uuids = array_values(array_unique(array_filter($uuids, 'is_string')));
        $names = [];
        foreach (array_chunk($uuids, 500) as $chunk) {
            $query = (new Profile())->getConnection()->table($table)->whereIn('uuid', $chunk);
            if ($ofCompany) {
                $query->where('company_uuid', $this->company);
            }
            foreach ($query->get(['uuid', 'name']) as $row) {
                if (is_object($row) && is_string($row->uuid ?? null)) {
                    $names[$row->uuid] = is_string($row->name ?? null) ? $row->name : '';
                }
            }
        }

        return $names;
    }
}
