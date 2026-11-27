import Component from '@glimmer/component';
import { tracked } from '@glimmer/tracking';
import { action } from '@ember/object';
import { inject as service } from '@ember/service';
import { kindOf, variantNames, newItem, moveItem, removeItem, problemsWithin } from '../../utils/profile-form';

// A list of profile elements of one kind: a section's rules, or a nested list such as a service
// rate's tiers. Elements are added (choosing a variant), moved and removed; rule order matters
// for fees and subtotal fees. A move or removal clears the problem marks within the list, since
// problems name positions. Elements are drawn by position, so a move or removal draws them again:
// text typed but not saved stays with no element rather than following another.
export default class PricingItemListComponent extends Component {
    @service intl;
    @tracked addVariant = null;
    @tracked drawn = 0;

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
                id: `${this.drawn}.${path}`,
                item,
                index,
                number: index + 1,
                path,
                title: this.titleOf(item, index),
                first: index === 0,
                last: index === last,
                problems: problemsWithin(this.args.env.problems, path).length,
            };
        });
    }

    // "Product 1 · Parcel fee": the number never changes as the name is typed.
    titleOf(item, index) {
        const key = `enhanced-pricing.console.item.${this.args.kind}`;
        const label = `${this.intl.exists(key) ? this.intl.t(key) : '#'} ${index + 1}`.replace('# ', '#');
        const name = item?.name || item?.key || item?.code;

        return name ? `${label} · ${name}` : label;
    }

    change(next, clearsProblems) {
        if (clearsProblems) {
            this.args.env.clearProblems(this.args.path);
            this.drawn++;
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
