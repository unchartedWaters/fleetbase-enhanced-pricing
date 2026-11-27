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
    @tracked found = null;
    @tracked left = [];

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
        this.found = null;
        this.region = '';
    }

    @action chooseRegion(event) {
        this.region = event.target.value;
        this.result = null;
        this.found = null;
    }

    @action typeYear(event) {
        this.year = event.target.value.trim();
        this.result = null;
        this.found = null;
    }

    @action chooseCount(event) {
        this.count = event.target.value;
        this.result = null;
        this.found = null;
    }

    get items() {
        const present = new Set(this.args.dates ?? []);

        return (this.found ?? []).map((holiday) => ({ ...holiday, present: present.has(holiday.date), chosen: !present.has(holiday.date) && !this.left.includes(holiday.date) }));
    }

    get chosen() {
        return this.items.filter((item) => item.chosen);
    }

    // Finds the holidays; none is added until the person has seen them and left out any they don't want.
    @action async find() {
        this.busy = true;
        this.refusal = null;
        this.result = null;
        this.found = null;
        this.left = [];
        const first = Number(this.year);
        try {
            const answer = await this.args.calendars.holidays(
                this.country,
                this.region,
                Array.from({ length: Number(this.count) }, (_, index) => first + index)
            );
            this.found = answer.holidays;
        } catch (refusal) {
            this.refusal = refusal ?? {};
        } finally {
            this.busy = false;
        }
    }

    @action toggleItem(date) {
        this.left = this.left.includes(date) ? this.left.filter((left) => left !== date) : [...this.left, date];
    }

    // Merges the chosen holidays into the list. One that would pass the list's limit changes nothing.
    @action confirm() {
        const present = new Set(this.args.dates ?? []);
        const added = this.chosen;
        const merged = [...new Set([...present, ...added.map((holiday) => holiday.date)])].sort();
        if (merged.length > this.args.max) {
            this.result = { tooMany: true, max: this.args.max };

            return;
        }
        this.result = { added, existing: this.items.filter((item) => item.present).length };
        this.found = null;
        this.args.onChange(merged);
    }
}
