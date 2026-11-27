import Component from '@glimmer/component';
import { action } from '@ember/object';
import { inject as service } from '@ember/service';
import { blankFor, newItem, problemsAt, refOptions, helpFor } from '../../utils/profile-form';

// One field of a profile element, drawn from its spec: a value, a nested object, a list of
// elements, or a choice between kinds of value (such as a price typed in or read from a column).
// @env holds what every field shares: the form spec, the whole draft, the problems, the
// currency, and whether the profile is read-only or new.
export default class PricingFieldComponent extends Component {
    @service intl;

    get field() {
        return this.args.field;
    }

    get env() {
        return this.args.env;
    }

    // The hover help: the field's own text for its kind, else the text every field of that name
    // shares. Nothing is shown for a field with neither.
    get help() {
        return helpFor(this.intl, this.args.kind, this.field.name);
    }

    get problems() {
        return problemsAt(this.env.problems, this.args.path);
    }

    get disabled() {
        return this.env.readOnly || (this.field.fixed && !this.env.isNew);
    }

    get isScalar() {
        return !['object', 'list', 'choice'].includes(this.field.type);
    }

    // An optional flag the profile leaves out takes its default, so the box shows that, not "off".
    // Nothing is written until the person changes it.
    get scalarValue() {
        const field = this.field;
        if (this.args.value === undefined && field.type === 'boolean' && field.optional && 'default' in field) {
            return field.default;
        }

        return this.args.value;
    }

    get options() {
        if (this.field.type === 'enum') {
            return this.field.options;
        }

        return refOptions(this.field.ref, this.env.definition, this.args.context);
    }

    get choiceNames() {
        return Object.keys(this.field.choices ?? {});
    }

    // A plain number is a value: profiles saved before amounts could come from a column hold one.
    get choiceObject() {
        const value = this.args.value;
        if (typeof value === 'number') {
            return { value };
        }

        return value !== null && typeof value === 'object' ? value : null;
    }

    get chosen() {
        const value = this.choiceObject;

        if (!value) {
            return null;
        }

        return this.choiceNames.find((name) => name in value) ?? null;
    }

    get chosenSpec() {
        return this.chosen ? this.field.choices[this.chosen] : null;
    }

    // A column is chosen after the rate table it is in, which sits beside it (the spec's `with`).
    get withName() {
        return this.chosenSpec?.with ?? null;
    }

    get withOptions() {
        return refOptions('rate_tables', this.env.definition);
    }

    get chosenContext() {
        return this.withName ? { [this.withName]: this.choiceObject?.[this.withName] ?? '' } : this.args.context;
    }

    get chosenOptions() {
        const spec = this.chosenSpec;

        return spec?.type === 'ref' ? refOptions(spec.ref, this.env.definition, this.chosenContext) : [];
    }

    @action setChoiceKind(event) {
        const name = event.target.value;
        if (name === '') {
            this.args.onChange(null);

            return;
        }
        const spec = this.field.choices[name];
        this.args.onChange({ [name]: spec.type === 'text' ? '' : null, ...(spec.with ? { [spec.with]: null } : {}) });
    }

    @action setChoiceValue(value) {
        // A cleared value box on a field that may be unset means unset, not { value: null }.
        if (value === null && this.field.nullable && !this.withName) {
            this.args.onChange(null);

            return;
        }
        this.args.onChange({ ...this.choiceObject, [this.chosen]: value });
    }

    // Changing the table clears the column, which may not be in the new one.
    @action setWith(value) {
        this.args.onChange({ [this.chosen]: null, [this.withName]: value });
    }

    @action addObject() {
        this.args.onChange(newItem(this.env.form, this.field.of));
    }

    @action removeObject() {
        this.args.onChange(null);
    }

    @action reportError(failing) {
        this.env.onError(this.args.path, failing);
    }

    blank() {
        return blankFor(this.env.form, this.field);
    }
}
