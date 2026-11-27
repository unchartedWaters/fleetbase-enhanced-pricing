<?php

use Fleetbase\EnhancedPricing\Admin\AdminRefused;
use Fleetbase\EnhancedPricing\Admin\HolidayCalendars;

// The console's holiday calendar endpoints: the catalog of countries and regions, and the dates of
// one country and region for some years. They read bundled data only, so they need no company.

function holidayRefusal(Closure $call): array
{
    try {
        $call();
    } catch (AdminRefused $refused) {
        return ['status' => $refused->status] + $refused->body;
    }

    throw new LogicException('not refused');
}

test('the catalog lists the countries with their regions', function () {
    $catalog = HolidayCalendars::catalog();

    expect($catalog['countries'])->toHaveKeys(['US', 'CA'])
        ->and($catalog['countries']['CA']['regions']['QC'])->toBe('Quebec')
        ->and($catalog['limits'])->toBe(['max_years' => 5, 'first_year' => 1900, 'last_year' => 2300]);
});

test('a country, region and years give their holiday dates', function () {
    $out = HolidayCalendars::list('CA', 'QC', '2026,2027');

    expect($out['holidays'])->toContain(['date' => '2026-06-24', 'name' => 'Fête nationale du Québec', 'observed' => false])
        ->and(array_column($out['holidays'], 'date'))->toContain('2027-06-24')
        ->and($out['country'])->toBe('CA')
        ->and($out['region'])->toBe('QC');
});

test('bad input is refused with a problem the console can show', function (mixed $country, mixed $region, mixed $years, string $key, string $path) {
    $refusal = holidayRefusal(fn () => HolidayCalendars::list($country, $region, $years));

    expect($refusal['status'])->toBe(422)
        ->and($refusal['refused'])->toBe('invalid')
        ->and($refusal['problems'][0]['key'])->toBe("enhanced-pricing.admin.$key")
        ->and($refusal['problems'][0]['path'])->toBe($path);
})->with([
    'unknown country'  => ['ZZ', null, '2026', 'holiday-unknown-country', 'country'],
    'lower case'       => ['us', null, '2026', 'holiday-unknown-country', 'country'],
    'path in country'  => ['../US', null, '2026', 'holiday-unknown-country', 'country'],
    'unknown region'   => ['US', 'ZZ', '2026', 'holiday-unknown-region', 'region'],
    'no region'        => ['US', null, '2026', 'holiday-unknown-region', 'region'],
    'blank region'     => ['US', '', '2026', 'holiday-unknown-region', 'region'],
    'region not text'  => ['US', ['TX'], '2026', 'holiday-unknown-region', 'region'],
    'no years'         => ['US', null, '', 'holiday-years', 'years'],
    'years not given'  => ['US', null, null, 'holiday-years', 'years'],
    'years not digits' => ['US', null, '20x6', 'holiday-years', 'years'],
    'too many years'   => ['US', null, '2020,2021,2022,2023,2024,2025', 'holiday-years', 'years'],
    'year too early'   => ['US', null, '1500', 'holiday-years', 'years'],
    'year as array'    => ['US', null, ['2026'], 'holiday-years', 'years'],
]);
