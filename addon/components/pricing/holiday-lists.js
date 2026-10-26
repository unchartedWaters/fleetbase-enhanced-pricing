import Component from '@glimmer/component';
import { tracked } from '@glimmer/tracking';
import { action } from '@ember/object';
import { mapToEntries, entriesToMap, problemsWithin } from '../../utils/profile-form';

// Holiday lists: a map of list name to dates, edited as named lists. A renamed list keeps its
// place; a fee names a list by its name.
export default class PricingHolidayListsComponent extends Component {
    @tracked newName = '';

    get entries() {
        return mapToEntries(this.args.value).map((entry, index) => ({
            ...entry,
            index,
            path: `holiday_lists.${entry.name}`,
            problems: problemsWithin(this.args.env.problems, `holiday_lists.${entry.name}`),
        }));
    }

    get canAdd() {
        const name = this.newName.trim();

        return !this.args.env.readOnly && name !== '' && !mapToEntries(this.args.value).some((entry) => entry.name === name) && this.entries.length < (this.args.max ?? 10);
    }

    save(entries) {
        this.args.env.clearProblems('holiday_lists');
        this.args.onChange(entriesToMap(entries));
    }

    @action rename(index, event) {
        const entries = mapToEntries(this.args.value);
        entries[index] = { ...entries[index], name: event.target.value.trim() };
        this.save(entries);
    }

    @action setDates(index, dates) {
        const entries = mapToEntries(this.args.value);
        entries[index] = { ...entries[index], dates };
        this.args.onChange(entriesToMap(entries));
    }

    @action remove(index) {
        this.save(mapToEntries(this.args.value).filter((_, position) => position !== index));
    }

    @action typeName(event) {
        this.newName = event.target.value;
    }

    @action add() {
        this.args.onChange(entriesToMap([...mapToEntries(this.args.value), { name: this.newName.trim(), dates: [] }]));
        this.newName = '';
    }
}
