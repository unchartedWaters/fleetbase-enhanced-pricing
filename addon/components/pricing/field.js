import Component from '@glimmer/component';
import { action } from '@ember/object';
import { blankFor, newItem, problemsAt, refOptions } from '../../utils/profile-form';

// One field of a profile element, drawn from its spec: a value, a nested object, a list of
// elements, or a choice between kinds of value (such as a price typed in or read from a column).
// @env holds what every field shares: the form spec, the whole draft, the problems, the
// currency, and whether the profile is read-only or new.
export default class PricingFieldComponent extends Component {
    get field() {
        return this.args.field;
    }

    get env() {
        return this.args.env;
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

    get chosen() {
        const value = this.args.value;
        if (value === null || value === undefined || typeof value !== 'object') {
            return null;
        }

        return this.choiceNames.find((name) => name in value) ?? null;
    }

    get chosenSpec() {
        return this.chosen ? this.field.choices[this.chosen] : null;
    }

    get chosenOptions() {
        const spec = this.chosenSpec;

        return spec?.type === 'ref' ? refOptions(spec.ref, this.env.definition, this.args.context) : [];
    }

    @action setChoiceKind(event) {
        const name = event.target.value;
        if (name === '') {
            this.args.onChange(null);

            return;
        }
        this.args.onChange({ [name]: this.field.choices[name].type === 'text' ? '' : null });
    }

    @action setChoiceValue(value) {
        this.args.onChange({ [this.chosen]: value });
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
