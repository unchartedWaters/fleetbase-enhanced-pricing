<?php

namespace Fleetbase\EnhancedPricing\Admin;

use Illuminate\Database\Eloquent\Builder;

/**
 * Row counts grouped by one column, in one query, so a list doesn't count row by row.
 */
final class Counts
{
    /**
     * @return array<string, int>
     */
    public static function by(Builder $query, string $column): array
    {
        $counts = [];
        $rows   = $query->getQuery()->selectRaw("$column as grouped, count(*) as total")->groupBy($column)->get();
        foreach ($rows as $row) {
            if (is_object($row) && is_string($row->grouped ?? null) && is_numeric($row->total ?? null)) {
                $counts[$row->grouped] = (int) $row->total;
            }
        }

        return $counts;
    }
}
