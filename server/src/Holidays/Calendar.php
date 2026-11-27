<?php

namespace Fleetbase\EnhancedPricing\Holidays;

/**
 * Holidays by country and region, worked out from the rules in server/data/holidays (one JSON file
 * per country; the format is in docs/holidays.md). Nothing is fetched and nothing is stored: a
 * request names a country, a region and years, and gets plain calendar dates back, which the
 * console puts into a holiday list. The list is the profile's own data from then on.
 *
 * A holiday that falls on a weekend can also be observed on a weekday. Both dates come back, the
 * second named "(observed)", because a fee on a holiday list applies to either day.
 *
 * @phpstan-type Rule array<string, int|string>
 * @phpstan-type Holiday array{id: string, name: string, rule: Rule, observed: string, regions: string|list<string>|array{except: list<string>}, since?: int, until?: int}
 * @phpstan-type Country array{country: string, name: string, regions: array<string, string>, holidays: list<Holiday>}
 */
final class Calendar
{
    public const FIRST_YEAR = 1900;
    public const LAST_YEAR  = 2300;
    public const MAX_YEARS  = 5;

    private static ?self $default = null;

    /**
     * @param array<string, Country> $countries country code => the data file's content
     */
    private function __construct(private readonly array $countries)
    {
    }

    /**
     * @param array<string, Country> $countries
     */
    public static function fromData(array $countries): self
    {
        return new self($countries);
    }

    private static function bundled(): self
    {
        if (self::$default === null) {
            $countries = [];
            foreach (glob(__DIR__ . '/../../data/holidays/*.json') ?: [] as $file) {
                /** @var Country $data the shape tests/HolidaysTest.php holds every file to */
                $data                                = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
                $countries[basename($file, '.json')] = $data;
            }
            ksort($countries);
            self::$default = new self($countries);
        }

        return self::$default;
    }

    /**
     * @return array<string, array{name: string, regions: array<string, string>}>
     */
    public static function countries(): array
    {
        return self::bundled()->catalog();
    }

    /**
     * @param list<int> $years
     *
     * @return list<array{date: string, name: string, observed: bool}>
     */
    public static function holidays(string $country, ?string $region, array $years): array
    {
        return self::bundled()->expand($country, $region, $years);
    }

    /**
     * @return array<string, array{name: string, regions: array<string, string>}>
     */
    public function catalog(): array
    {
        $catalog = [];
        foreach ($this->countries as $code => $data) {
            $catalog[$code] = ['name' => $data['name'], 'regions' => $data['regions']];
        }

        return $catalog;
    }

    /**
     * The holidays of the country, in the region (or, with no region, those that apply everywhere),
     * for the years: one entry per date, oldest first.
     *
     * @param list<int> $years
     *
     * @return list<array{date: string, name: string, observed: bool}>
     */
    public function expand(string $country, ?string $region, array $years): array
    {
        $data = $this->countries[$country] ?? throw new \InvalidArgumentException('unknown-country');
        if ($region !== null && array_key_exists($region, $data['regions']) === false) {
            throw new \InvalidArgumentException('unknown-region');
        }
        $years = array_values(array_unique($years));
        if ($years === [] || count($years) > self::MAX_YEARS) {
            throw new \InvalidArgumentException('years-count');
        }
        foreach ($years as $year) {
            if ($year < self::FIRST_YEAR || $year > self::LAST_YEAR) {
                throw new \InvalidArgumentException('years-range');
            }
        }

        // A holiday observed across New Year belongs to the neighbouring year's date list, so the
        // years on both sides are worked out, and only the requested ones are kept.
        /** @var list<array{0: \DateTimeImmutable, 1: string, 2: bool}> $found */
        $found = [];
        foreach ($data['holidays'] as $holiday) {
            if ($this->applies($holiday['regions'], $region) === false) {
                continue;
            }
            foreach ($this->yearsAround($years) as $year) {
                if (($holiday['since'] ?? self::FIRST_YEAR) > $year || ($holiday['until'] ?? self::LAST_YEAR) < $year) {
                    continue;
                }
                $date = $this->date($holiday['rule'], $year);
                if ($date === null) {
                    continue;
                }
                $found[]  = [$date, $holiday['name'], false];
                $observed = $this->observed($date, $holiday['observed']);
                if ($observed !== null) {
                    $found[] = [$observed, $holiday['name'] . ' (observed)', true];
                }
            }
        }

        /** @var array<string, array{names: list<string>, observed: bool}> $byDate */
        $byDate = [];
        foreach ($found as [$date, $name, $observed]) {
            if (in_array((int) $date->format('Y'), $years, true) === false) {
                continue;
            }
            $key                      = $date->format('Y-m-d');
            $byDate[$key] ??= ['names' => [], 'observed' => true];
            $byDate[$key]['names'][]  = $name;
            $byDate[$key]['observed'] = $byDate[$key]['observed'] && $observed;
        }
        ksort($byDate);

        $out = [];
        foreach ($byDate as $date => $entry) {
            $out[] = ['date' => $date, 'name' => implode(' / ', array_unique($entry['names'])), 'observed' => $entry['observed']];
        }

        return $out;
    }

    /**
     * @param list<int> $years
     *
     * @return list<int>
     */
    private function yearsAround(array $years): array
    {
        $all = [];
        foreach ($years as $year) {
            array_push($all, $year - 1, $year, $year + 1);
        }

        return array_values(array_unique($all));
    }

    /**
     * @param string|list<string>|array{except: list<string>} $regions
     */
    private function applies(string|array $regions, ?string $region): bool
    {
        if ($regions === 'all') {
            return true;
        }
        if ($region === null || is_array($regions) === false) {
            return false;
        }
        if (array_key_exists('except', $regions)) {
            return in_array($region, (array) $regions['except'], true) === false;
        }

        return in_array($region, $regions, true);
    }

    /**
     * @param Rule $rule
     */
    private function date(array $rule, int $year): ?\DateTimeImmutable
    {
        $month   = (int) ($rule['month'] ?? 0);
        $day     = (int) ($rule['day'] ?? 0);
        $weekday = (int) ($rule['weekday'] ?? 0);
        $type    = (string) $rule['type'];
        $date    = match ($type) {
            'fixed'             => $this->day($year, $month, $day),
            'nth_weekday'       => $this->nthWeekday($year, $month, $weekday, (int) ($rule['nth'] ?? 0)),
            'weekday_before'    => $this->shiftToWeekday($this->day($year, $month, $day), $weekday, -1, false),
            'nth_weekday_after' => $this->shiftToWeekday($this->day($year, $month, $day), $weekday, 1, true),
            'easter'            => $this->easter($year),
            default             => throw new \LogicException('Unknown holiday rule ' . $type),
        };

        return $date === null ? null : $this->addDays($date, (int) ($rule['offset_days'] ?? 0));
    }

    private function addDays(\DateTimeImmutable $date, int $days): \DateTimeImmutable
    {
        $interval = new \DateInterval('P' . abs($days) . 'D');

        return $days < 0 ? $date->sub($interval) : $date->add($interval);
    }

    private function day(int $year, int $month, int $day): \DateTimeImmutable
    {
        return new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day), new \DateTimeZone('UTC'));
    }

    /**
     * Step from the date to the weekday (ISO 1 Monday to 7 Sunday), forward or back, counting the
     * date itself only when $inclusive.
     */
    private function shiftToWeekday(\DateTimeImmutable $date, int $weekday, int $step, bool $inclusive): \DateTimeImmutable
    {
        if ($inclusive === false) {
            $date = $this->addDays($date, $step);
        }
        while ((int) $date->format('N') !== $weekday) {
            $date = $this->addDays($date, $step);
        }

        return $date;
    }

    private function nthWeekday(int $year, int $month, int $weekday, int $nth): ?\DateTimeImmutable
    {
        if ($nth < 0) {
            $first = $this->day($year, $month, 1);

            return $this->shiftToWeekday($first->setDate($year, $month, (int) $first->format('t')), $weekday, -1, true);
        }
        $date = $this->shiftToWeekday($this->day($year, $month, 1), $weekday, 1, true)->add(new \DateInterval('P' . (($nth - 1) * 7) . 'D'));

        return (int) $date->format('n') === $month ? $date : null;
    }

    /**
     * Western Easter Sunday, by the Meeus/Jones/Butcher computus (Gregorian calendar).
     */
    private function easter(int $year): \DateTimeImmutable
    {
        $a     = $year % 19;
        $b     = intdiv($year, 100);
        $c     = $year % 100;
        $d     = intdiv($b, 4);
        $e     = $b % 4;
        $f     = intdiv($b + 8, 25);
        $g     = intdiv($b - $f + 1, 3);
        $h     = (19 * $a + $b - $d - $g + 15) % 30;
        $i     = intdiv($c, 4);
        $k     = $c % 4;
        $l     = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m     = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day   = (($h + $l - 7 * $m + 114) % 31) + 1;

        return $this->day($year, $month, $day);
    }

    /**
     * The weekday a weekend holiday is observed on, or null when it needs none.
     */
    private function observed(\DateTimeImmutable $date, string $policy): ?\DateTimeImmutable
    {
        $weekday = (int) $date->format('N');
        if ($policy === 'sat-fri-sun-mon' && $weekday === 6) {
            return $this->addDays($date, -1);
        }

        return $policy !== 'none' && $weekday === 7 ? $this->addDays($date, 1) : null;
    }
}
