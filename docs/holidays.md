# Holiday calendars

A profile's holiday lists are plain lists of dates (see
[Profile format](profile-format.md#holiday-lists)). The profile editor can fill
a list from a country's calendar: choose a country, a state or province, the
first year and how many years (1 to 5), then **Show holidays**. The holidays found are listed with a
tick box each: untick any you don't want, then add the rest. Dates already in
the list are marked and left alone. The dates are merged into the list, sorted,
without duplicates. The list is also an ordinary text box of dates, one per
line, so a date can be added by hand and any line removed at any time, including
a filled one. Removing a date is not remembered: filling again shows it again. A
fill that would pass the 400 dates a list holds changes nothing. Nothing is
saved until the profile is saved, and nothing is stored or fetched at run time:
the rules are bundled with the extension and the list is the profile's own data
afterwards, so a later change to the rules never changes a saved profile.

## Observed days

When a holiday falls on a weekend, some calendars also observe it on a weekday.
Both dates are added, the second named "(observed)", because a fee on a holiday
list applies to either day. The weekday can fall in the neighbouring year (New
Year's Day 2028 is a Saturday, so the US federal government observes Friday
2027-12-31, which is in 2027's dates).

- **United States**: federal rule, 5 U.S.C. 6103(b): Saturday is observed on
  Friday and Sunday on Monday.
- **Canada**: most laws give employees a substitute day (chosen by the
  employer or the next working day) rather than a fixed date, so no day is
  added. The exceptions are the fixed Sunday-to-Monday shifts of Alberta's and
  Quebec's Canada Day and Quebec's Fête nationale.

## Endpoints

Both need the `see extension` permission.

- `GET enhanced-pricing/int/v1/admin/holiday-calendars`: `{ countries: { US: { name, regions: { TX: "Texas", ... } }, ... }, limits }`.
- `GET enhanced-pricing/int/v1/admin/holiday-calendars/{country}?region=TX&years=2026,2027`:
  `{ country, region, holidays: [{ date, name, observed }] }`. Several holidays on
  one date are one entry. An unknown country, a missing or unknown region, or years that are not 1
  to 5 four-digit years from 1900 to 2300, is refused (422, `invalid`).

## Adding or correcting a country

One JSON file per country in `server/data/holidays/<ISO 3166-1 alpha-2>.json`,
found automatically. Tests check each file's shape and that every rule works in
every region.

```json
{
  "country": "XX",
  "name": "Example",
  "regions": { "AA": "Region A", "BB": "Region B" },
  "holidays": [
    { "id": "new-years-day", "name": "New Year's Day", "rule": { "type": "fixed", "month": 1, "day": 1 }, "observed": "none", "regions": "all" },
    { "id": "founders-day", "name": "Founders' Day", "rule": { "type": "nth_weekday", "month": 3, "weekday": 1, "nth": 1 }, "observed": "none", "regions": ["AA"], "since": 2010 }
  ]
}
```

- `regions` (of the holiday): `"all"`, a list of region codes, or `{ "except": [...] }`.
- `since` and `until` (optional): first and last year the holiday applies.
- `observed`: `none`, `sun-mon` (a Sunday is also observed Monday) or `sat-fri-sun-mon`.
- Rules (weekday 1 is Monday to 7 Sunday):
  - `fixed`: `month`, `day`.
  - `nth_weekday`: `month`, `weekday`, `nth` (1 to 5, or -1 for the last).
  - `weekday_before`: the weekday strictly before `month`/`day`.
  - `nth_weekday_after`: the first weekday on or after `month`/`day`.
  - `easter`: Western Easter Sunday.
  - Any rule may have `offset_days` (for example 1 for the day after).

Only legal public or statutory holidays belong here, not observances. Take
dates from the law or the government's published list, and add a test with the
known dates.

## Sources and gaps

The datasets were built from each jurisdiction's own statute or government page
(see [Holiday data sources](holidays-sources.md)), and checked against an
independent calendar library for 2026 to 2028. Known gaps:

- **Canada, Quebec**: the law lets the employer choose Good Friday or Easter
  Monday, so neither is included.
- **Canada, Newfoundland and Labrador**: only the days its Labour Standards Act
  names are included; days that apply only to government employees are not.
- **Canada, Nunavut**: the weekend rule could not be confirmed.
- **Employer or contract holidays** (Christmas Eve, the day after
  Thanksgiving where not a state holiday, and so on) are not included: add
  them to the list by hand.
- **United States**: state schedules were read from each state's own page or
  statute, but several rest on one year's schedule (see the sources file).
  Even-year-only Election Days, governor-proclaimed and one-off closure days,
  and local-only days are left out. Rhode Island moves a Saturday holiday to
  Monday too, and California moves Veterans Day on a Saturday to Friday;
  neither is modelled. Juneteenth is included from 2021 in every state that
  lists it, whatever year the state adopted it. The weekend rule per holiday
  follows the state's own page where one was found.
