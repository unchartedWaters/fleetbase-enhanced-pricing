import Component from '@glimmer/component';
import { tracked } from '@glimmer/tracking';
import { action } from '@ember/object';
import { kindOf, newItem, variantNames } from '../../utils/profile-form';

// The Fees page: the profile's fees, subtotal fees and manual charges together. They stay three
// lists of the profile (the engine charges them in that order), each drawn by an item list; a
// single "Add" chooses what kind of fee to add, from the variants of the three lists.
export default class PricingFeeListComponent extends Component {
    @tracked chosen = null;

    get groups() {
        return this.args.members.map((member) => ({ ...member, items: this.args.draft?.[member.key] ?? [] }));
    }

    // Every kind of fee that can still be added: a list's variants, until its limit.
    get types() {
        return this.groups.flatMap((group) => {
            if (group.max !== undefined && group.items.length >= group.max) {
                return [];
            }

            return variantNames(kindOf(this.args.env.form, group.kind)).map((variant) => ({ id: `${group.key}-${variant}`, list: group.key, kind: group.kind, variant }));
        });
    }

    get type() {
        return this.types.find((type) => type.id === this.chosen) ?? this.types[0];
    }

    get empty() {
        return this.groups.every((group) => group.items.length === 0);
    }

    @action choose(event) {
        this.chosen = event.target.value;
    }

    @action add() {
        const { list, kind, variant } = this.type;
        this.args.onChange(list, [...(this.args.draft?.[list] ?? []), newItem(this.args.env.form, kind, variant)]);
    }
}
