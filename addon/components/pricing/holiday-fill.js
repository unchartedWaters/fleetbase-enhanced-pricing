import Component from '@glimmer/component';
import { tracked } from '@glimmer/tracking';
import { action } from '@ember/object';

// Fills a holiday list from a country's calendar. The server works out the dates (observed days
// included); this picks the country, region and years, and merges the dates into the list. The
// list is only changed in the draft: nothing is saved until the profile is. A fill that would
// pass the list's limit changes nothing.
export default class PricingHolidayFillComponent extends Component {
    @tracked open = false;
    @tracked catalog = null;
    @tracked country = '';
    @tracked region = '';
    @tracked year = String(new Date().getFullYear());
    @tracked count = '2';
    @tracked busy = false;
    @tracked refusal = null;
    @tracked result = null;

    get countries() {
        return Object.entries(this.catalog?.countries ?? {}).map(([code, country]) => ({ code, name: country.name }));
    }

    get regions() {
        return Object.entries(this.catalog?.countries?.[this.country]?.regions ?? {})
            .map(([code, name]) => ({ code, name }))
            .sort((a, b) => a.name.localeCompare(b.name));
    }

    get counts() {
        return Array.from({ length: this.catalog?.limits?.max_years ?? 5 }, (_, index) => String(index + 1));
    }

    get yearValid() {
        const limits = this.catalog?.limits;

        return /^\d{4}$/.test(this.year) && Number(this.year) >= (limits?.first_year ?? 1900) && Number(this.year) + Number(this.count) - 1 <= (limits?.last_year ?? 2300);
    }

    get canAdd() {
        return this.country !== '' && this.region !== '' && this.yearValid && !this.busy;
    }

    @action async toggle() {
        this.open = !this.open;
        if (this.open && this.catalog === null) {
            this.refusal = null;
            try {
                this.catalog = await this.args.calendars.catalog();
            } catch (refusal) {
                this.refusal = refusal ?? {};
            }
        }
    }

    @action chooseCountry(event) {
        this.country = event.target.value;
        this.result = null;
        this.region = '';
    }

    @action chooseRegion(event) {
        this.region = event.target.value;
        this.result = null;
    }

    @action typeYear(event) {
        this.year = event.target.value.trim();
        this.result = null;
    }

    @action chooseCount(event) {
        this.count = event.target.value;
        this.result = null;
    }

    @action async add() {
        this.busy = true;
        this.refusal = null;
        this.result = null;
        const first = Number(this.year);
        try {
            const answer = await this.args.calendars.holidays(
                this.country,
                this.region,
                Array.from({ length: Number(this.count) }, (_, index) => first + index)
            );
            this.merge(answer.holidays);
        } catch (refusal) {
            this.refusal = refusal ?? {};
        } finally {
            this.busy = false;
        }
    }

    merge(holidays) {
        const present = new Set(this.args.dates ?? []);
        const added = holidays.filter((holiday) => !present.has(holiday.date));
        const merged = [...new Set([...present, ...added.map((holiday) => holiday.date)])].sort();
        if (merged.length > this.args.max) {
            this.result = { tooMany: true, max: this.args.max };

            return;
        }
        this.result = { added, existing: holidays.length - added.length };
        this.args.onChange(merged);
    }
}
