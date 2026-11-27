<?php

use Fleetbase\EnhancedPricing\Holidays\Calendar;

// Holiday lists can be filled from a country's calendar. The rules are data in server/data/holidays;
// these tests hold the engine to the statutes' calendar rules and the data to the dates below, each
// taken from the law or the government's own published list (see docs/holidays.md for sources).

/**
 * @return array<string, string> date => name, for the country and region
 */
function holidayDates(string $country, ?string $region, int $year): array
{
    $dates = [];
    foreach (Calendar::holidays($country, $region, [$year]) as $holiday) {
        $dates[$holiday['date']] = $holiday['name'];
    }

    return $dates;
}

/**
 * A calendar from rules, so the engine is tested apart from the data.
 *
 * @param array<string, mixed> $rule
 *
 * @return list<string>
 */
function ruleDates(array $rule, string $observed, int $year): array
{
    $calendar = Calendar::fromData(['XX' => [
        'country'  => 'XX',
        'name'     => 'Test',
        'regions'  => ['AA' => 'Region A'],
        'holidays' => [['id' => 'h', 'name' => 'H', 'rule' => $rule, 'observed' => $observed, 'regions' => 'all']],
    ]]);

    return array_column($calendar->expand('XX', 'AA', [$year]), 'date');
}

test('a fixed date is the same date every year', function () {
    expect(ruleDates(['type' => 'fixed', 'month' => 7, 'day' => 1], 'none', 2026))->toBe(['2026-07-01']);
});

test('the nth weekday of a month, and the last one, count from ISO weekdays', function () {
    // Thanksgiving: fourth Thursday of November. 2026-11-26 and 2027-11-25 are the known dates.
    $thanksgiving = ['type' => 'nth_weekday', 'month' => 11, 'weekday' => 4, 'nth' => 4];
    expect(ruleDates($thanksgiving, 'none', 2026))->toBe(['2026-11-26'])
        ->and(ruleDates($thanksgiving, 'none', 2027))->toBe(['2027-11-25']);
    // Memorial Day: last Monday of May.
    $memorial = ['type' => 'nth_weekday', 'month' => 5, 'weekday' => 1, 'nth' => -1];
    expect(ruleDates($memorial, 'none', 2026))->toBe(['2026-05-25'])
        ->and(ruleDates($memorial, 'none', 2027))->toBe(['2027-05-31'])
        ->and(ruleDates($memorial, 'none', 2028))->toBe(['2028-05-29']);
    // A day after: Thanksgiving's Friday.
    expect(ruleDates($thanksgiving + ['offset_days' => 1], 'none', 2026))->toBe(['2026-11-27']);
});

test('the weekday before a date is strictly before it', function () {
    // Victoria Day: the Monday before May 25. May 25, 2026 is itself a Monday, so it is May 18.
    $victoria = ['type' => 'weekday_before', 'month' => 5, 'day' => 25, 'weekday' => 1];
    expect(ruleDates($victoria, 'none', 2026))->toBe(['2026-05-18'])
        ->and(ruleDates($victoria, 'none', 2027))->toBe(['2027-05-24'])
        ->and(ruleDates($victoria, 'none', 2028))->toBe(['2028-05-22']);
});

test('the first weekday on or after a date', function () {
    // Election Day: the Tuesday on or after November 2. 2026-11-03 and 2027-11-02.
    $election = ['type' => 'nth_weekday_after', 'month' => 11, 'day' => 2, 'weekday' => 2];
    expect(ruleDates($election, 'none', 2026))->toBe(['2026-11-03'])
        ->and(ruleDates($election, 'none', 2027))->toBe(['2027-11-02']);
});

test('Easter and the days around it follow the Gregorian computus', function () {
    // Easter Sunday: 2026-04-05, 2027-03-28, 2028-04-16, 2038-04-25 (the latest possible), 2285-03-22 (the earliest).
    foreach ([2026 => '04-05', 2027 => '03-28', 2028 => '04-16', 2038 => '04-25', 2285 => '03-22'] as $year => $day) {
        expect(ruleDates(['type' => 'easter', 'offset_days' => 0], 'none', $year))->toBe(["$year-$day"]);
    }
    expect(ruleDates(['type' => 'easter', 'offset_days' => -2], 'none', 2026))->toBe(['2026-04-03'])
        ->and(ruleDates(['type' => 'easter', 'offset_days' => 1], 'none', 2027))->toBe(['2027-03-29']);
});

test('a weekend holiday adds its observed day under the sat-fri-sun-mon rule', function () {
    // US federal, 5 U.S.C. 6103(b): Saturday is observed Friday, Sunday Monday.
    $july4 = ['type' => 'fixed', 'month' => 7, 'day' => 4];
    expect(ruleDates($july4, 'sat-fri-sun-mon', 2026))->toBe(['2026-07-03', '2026-07-04'])
        ->and(ruleDates($july4, 'sat-fri-sun-mon', 2027))->toBe(['2027-07-04', '2027-07-05'])
        ->and(ruleDates($july4, 'sat-fri-sun-mon', 2028))->toBe(['2028-07-04']);
});

test('the sun-mon rule moves only a Sunday', function () {
    $day = ['type' => 'fixed', 'month' => 7, 'day' => 4];
    expect(ruleDates($day, 'sun-mon', 2026))->toBe(['2026-07-04'])
        ->and(ruleDates($day, 'sun-mon', 2027))->toBe(['2027-07-04', '2027-07-05']);
});

test('an observed day in the neighbouring year is kept for the year it falls in', function () {
    // New Year's Day 2028 is a Saturday, so the federal government observes Friday 2027-12-31.
    $newYear = ['type' => 'fixed', 'month' => 1, 'day' => 1];
    expect(ruleDates($newYear, 'sat-fri-sun-mon', 2027))->toBe(['2027-01-01', '2027-12-31'])
        ->and(ruleDates($newYear, 'sat-fri-sun-mon', 2028))->toBe(['2028-01-01'])
        // A Sunday New Year's Day, 2023-01-01, is observed Monday the 2nd.
        ->and(ruleDates($newYear, 'sat-fri-sun-mon', 2023))->toBe(['2023-01-01', '2023-01-02']);
});

test('a holiday has first and last years', function () {
    $calendar = Calendar::fromData(['XX' => [
        'country'  => 'XX',
        'name'     => 'Test',
        'regions'  => ['AA' => 'A'],
        'holidays' => [[
            'id'       => 'h', 'name' => 'H', 'rule' => ['type' => 'fixed', 'month' => 6, 'day' => 19],
            'observed' => 'none', 'regions' => 'all', 'since' => 2021, 'until' => 2030,
        ]],
    ]]);

    expect($calendar->expand('XX', 'AA', [2020]))->toBe([])
        ->and(array_column($calendar->expand('XX', 'AA', [2021, 2030, 2031]), 'date'))->toBe(['2021-06-19', '2030-06-19']);
});

test('a holiday applies to all regions, listed regions, or all but some', function () {
    $holiday = static fn (string $id, mixed $regions, int $day): array => [
        'id' => $id, 'name' => $id, 'rule' => ['type' => 'fixed', 'month' => 1, 'day' => $day], 'observed' => 'none', 'regions' => $regions,
    ];
    $calendar = Calendar::fromData(['XX' => [
        'country'  => 'XX',
        'name'     => 'Test',
        'regions'  => ['AA' => 'A', 'BB' => 'B', 'CC' => 'C'],
        'holidays' => [$holiday('all', 'all', 2), $holiday('only-a', ['AA'], 3), $holiday('not-b', ['except' => ['BB']], 4)],
    ]]);
    $names = static fn (?string $region): array => array_column($calendar->expand('XX', $region, [2026]), 'name');

    expect($names('AA'))->toBe(['all', 'only-a', 'not-b'])
        ->and($names('BB'))->toBe(['all'])
        ->and($names('CC'))->toBe(['all', 'not-b'])
        // No region: only what applies everywhere.
        ->and($names(null))->toBe(['all']);
});

test('several years come back sorted, one entry per date', function () {
    $dates  = array_column(Calendar::holidays('US', 'NY', [2027, 2026, 2026]), 'date');
    $sorted = $dates;
    sort($sorted);

    expect($dates)->toBe(array_values(array_unique($dates)))
        ->and($dates)->toBe($sorted)
        ->and($dates[0])->toBe('2026-01-01');
});

test('two holidays on one date share one entry, named together', function () {
    $holiday  = static fn (string $name, string $observed): array => [
        'id' => $name, 'name' => $name, 'rule' => ['type' => 'fixed', 'month' => 7, 'day' => 4], 'observed' => $observed, 'regions' => 'all',
    ];
    $calendar = Calendar::fromData(['XX' => ['country' => 'XX', 'name' => 'T', 'regions' => ['AA' => 'A'], 'holidays' => [$holiday('One', 'none'), $holiday('Two', 'sat-fri-sun-mon')]]]);

    // 2026-07-04 is a Saturday: both holidays land on it, and Two is also observed on the 3rd.
    expect($calendar->expand('XX', 'AA', [2026]))->toBe([
        ['date' => '2026-07-03', 'name' => 'Two (observed)', 'observed' => true],
        ['date' => '2026-07-04', 'name' => 'One / Two', 'observed' => false],
    ]);
});

test('an unknown country, region or year is refused', function () {
    expect(fn () => Calendar::holidays('ZZ', null, [2026]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Calendar::holidays('US', 'ZZ', [2026]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Calendar::holidays('us', null, [2026]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Calendar::holidays('../US', null, [2026]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Calendar::holidays('US', null, [1500]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Calendar::holidays('US', null, []))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Calendar::holidays('US', null, range(2020, 2026)))->toThrow(InvalidArgumentException::class);
});

test('the catalog lists every country with its regions', function () {
    $countries = Calendar::countries();

    expect(array_keys($countries))->toContain('US', 'CA')
        ->and($countries['US']['regions'])->toHaveKey('TX')
        ->and($countries['US']['regions'])->toHaveKey('DC')
        ->and(count($countries['US']['regions']))->toBe(51)
        ->and(array_keys($countries['CA']['regions']))->toBe(['AB', 'BC', 'MB', 'NB', 'NL', 'NS', 'NT', 'NU', 'ON', 'PE', 'QC', 'SK', 'YT']);
});

test('every dataset is well formed', function () {
    foreach (glob(__DIR__ . '/../data/holidays/*.json') ?: [] as $file) {
        $data = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        $code = basename($file, '.json');
        expect($data['country'])->toBe($code)
            ->and($data['name'])->toBeString()
            ->and($data['regions'])->not->toBe([]);
        $ids = array_column($data['holidays'], 'id');
        expect($ids)->toBe(array_values(array_unique($ids)), "$code ids are unique");
        foreach ($data['holidays'] as $holiday) {
            expect(array_keys($holiday))->each->toBeIn(['id', 'name', 'rule', 'observed', 'regions', 'since', 'until']);
            expect($holiday['observed'])->toBeIn(['none', 'sat-fri-sun-mon', 'sun-mon']);
            $listed = is_array($holiday['regions']) ? ($holiday['regions']['except'] ?? $holiday['regions']) : [];
            foreach ($listed as $region) {
                expect($data['regions'])->toHaveKey($region, "$code {$holiday['id']} names an unknown region $region");
            }
            // Every rule expands in every region, for a long span.
            foreach (array_keys($data['regions']) as $region) {
                Calendar::holidays($code, $region, [2020, 2024, 2026, 2028, 2100]);
            }
        }
    }
});

test('New York, 2027: federal holidays with the observed Independence Day, and its own', function () {
    $dates = holidayDates('US', 'NY', 2027);

    expect($dates)->toHaveKey('2027-07-05')
        ->and($dates['2027-07-05'])->toBe('Independence Day (observed)')
        ->and($dates['2027-02-12'])->toBe("Lincoln's Birthday")
        ->and($dates['2027-11-25'])->toBe('Thanksgiving Day')
        ->and($dates)->not->toHaveKey('2027-11-26');
});

test('Juneteenth is a federal holiday from 2021', function () {
    expect(holidayDates('US', 'NY', 2020))->not->toHaveKey('2020-06-19')
        ->and(holidayDates('US', 'NY', 2021))->toHaveKey('2021-06-19');
});

test('Texas, California and Quebec differ from their country', function () {
    expect(holidayDates('US', 'TX', 2026))->toHaveKey('2026-11-27')   // Friday after Thanksgiving
        ->and(holidayDates('US', 'CA', 2026))->toHaveKey('2026-03-31') // Cesar Chavez Day
        ->and(holidayDates('US', 'MA', 2026))->toHaveKey('2026-04-20') // Patriots' Day, third Monday of April
        ->and(holidayDates('US', 'LA', 2026))->toHaveKey('2026-02-17'); // Mardi Gras, 47 days before Easter
});

test('Canadian provinces differ in their holidays, 2026', function () {
    // Quebec: Saint-Jean-Baptiste, National Patriots' Day instead of Victoria Day, no Family Day.
    expect(holidayDates('CA', 'QC', 2026))->toHaveKey('2026-06-24')
        ->and(holidayDates('CA', 'QC', 2026)['2026-05-18'])->toContain('Patriots')
        ->and(holidayDates('CA', 'QC', 2026))->not->toHaveKey('2026-02-16');
    // Family Day, third Monday of February, in Ontario, Alberta, Saskatchewan, BC, New Brunswick.
    foreach (['ON', 'AB', 'SK', 'BC', 'NB'] as $province) {
        expect(holidayDates('CA', $province, 2026))->toHaveKey('2026-02-16');
    }
    // Ontario has no Easter Monday and no Civic Holiday as statutory holidays.
    expect(holidayDates('CA', 'ON', 2026))->not->toHaveKey('2026-04-06')
        ->and(holidayDates('CA', 'ON', 2026))->not->toHaveKey('2026-08-03');
    expect(holidayDates('CA', 'ON', 2026))->toHaveKey('2026-05-18');
});
