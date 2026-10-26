import Component from '@glimmer/component';
import { tracked } from '@glimmer/tracking';
import { action } from '@ember/object';
import { kindOf, variantNames, newItem, moveItem, removeItem, problemsWithin } from '../../utils/profile-form';

// A list of profile elements of one kind: a section's rules, or a nested list such as a service
// rate's tiers. Elements are added (choosing a variant), moved and removed; rule order matters
// for fees and subtotal fees. A move or removal clears the problem marks within the list, since
// problems name positions.
export default class PricingItemListComponent extends Component {
    @tracked addVariant = null;

    get kind() {
        return kindOf(this.args.env.form, this.args.kind);
    }

    get items() {
        return Array.isArray(this.args.items) ? this.args.items : [];
    }

    get variants() {
        return variantNames(this.kind);
    }

    get chosenVariant() {
        return this.addVariant ?? this.variants[0] ?? null;
    }

    get canAdd() {
        return !this.args.env.readOnly && (this.args.max === undefined || this.items.length < this.args.max);
    }

    get entries() {
        const last = this.items.length - 1;

        return this.items.map((item, index) => {
            const path = `${this.args.path}.${index}`;

            return {
                item,
                index,
                number: index + 1,
                path,
                title: item?.name || item?.key || item?.code || `#${index + 1}`,
                first: index === 0,
                last: index === last,
                problems: problemsWithin(this.args.env.problems, path).length,
            };
        });
    }

    change(next, clearsProblems) {
        if (clearsProblems) {
            this.args.env.clearProblems(this.args.path);
        }
        this.args.onChange(next);
    }

    @action setItem(index, value) {
        const next = [...this.items];
        next[index] = value;
        this.change(next, false);
    }

    @action move(index, by) {
        this.change(moveItem(this.items, index, index + by), true);
    }

    @action remove(index) {
        this.change(removeItem(this.items, index), true);
    }

    @action chooseVariant(event) {
        this.addVariant = event.target.value;
    }

    @action add() {
        this.change([...this.items, newItem(this.args.env.form, this.args.kind, this.chosenVariant)], false);
    }
}
