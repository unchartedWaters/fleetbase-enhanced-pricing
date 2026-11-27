<?php

namespace Fleetbase\EnhancedPricing\Admin;

use Fleetbase\EnhancedPricing\Holidays\Calendar;

/**
 * The holiday calendars the console fills holiday lists from: the catalog of countries and their
 * regions, and the dates of one country and region for some years. Both are read from bundled
 * data (server/data/holidays), so nothing is company-specific and nothing is saved.
 */
final class HolidayCalendars
{
    /**
     * @return array{countries: array<string, array{name: string, regions: array<string, string>}>, limits: array{max_years: int, first_year: int, last_year: int}}
     */
    public static function catalog(): array
    {
        return [
            'countries' => Calendar::countries(),
            'limits'    => ['max_years' => Calendar::MAX_YEARS, 'first_year' => Calendar::FIRST_YEAR, 'last_year' => Calendar::LAST_YEAR],
        ];
    }

    /**
     * @return array{country: string, region: string, holidays: list<array{date: string, name: string, observed: bool}>}
     */
    public static function list(mixed $country, mixed $region, mixed $years): array
    {
        $catalog = Calendar::countries();
        if (is_string($country) === false || array_key_exists($country, $catalog) === false) {
            throw AdminRefused::invalid('holiday-unknown-country', [], 'country');
        }
        if (is_string($region) === false || array_key_exists($region, $catalog[$country]['regions']) === false) {
            throw AdminRefused::invalid('holiday-unknown-region', [], 'region');
        }

        return [
            'country'  => $country,
            'region'   => $region,
            'holidays' => Calendar::holidays($country, $region, self::years($years)),
        ];
    }

    /**
     * @return list<int>
     */
    private static function years(mixed $years): array
    {
        $parts = is_string($years) ? explode(',', $years) : [];
        $valid = $parts !== [] && count($parts) <= Calendar::MAX_YEARS;
        foreach ($parts as $part) {
            $valid = $valid && preg_match('/^\d{4}\z/', $part) === 1
                && (int) $part >= Calendar::FIRST_YEAR && (int) $part <= Calendar::LAST_YEAR;
        }
        if ($valid === false) {
            throw AdminRefused::invalid('holiday-years', ['max' => Calendar::MAX_YEARS, 'first' => Calendar::FIRST_YEAR, 'last' => Calendar::LAST_YEAR], 'years');
        }

        return array_map('intval', $parts);
    }
}
