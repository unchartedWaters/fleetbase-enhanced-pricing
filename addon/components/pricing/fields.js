import Component from '@glimmer/component';
import { action } from '@ember/object';
import { kindOf, variantNames, variantOf, fieldsFor, switchVariant, unknownFields, withoutKey } from '../../utils/profile-form';

// The fields of one profile element (or nested object) of a kind, with its variant chooser and
// any fields the format doesn't know, kept and removable. @context is the top element, which
// column and choice references are resolved against. @ignore names fields shown elsewhere (the
// profile's own sections, beside its general fields).
export default class PricingFieldsComponent extends Component {
    get kind() {
        return kindOf(this.args.env.form, this.args.kind);
    }

    get value() {
        return this.args.value ?? {};
    }

    get context() {
        return this.args.context ?? this.value;
    }

    get variants() {
        return variantNames(this.kind);
    }

    get variant() {
        return variantOf(this.kind, this.value);
    }

    get fields() {
        return fieldsFor(this.kind, this.value).map((field) => ({ field, path: this.pathOf(field.name) }));
    }

    get unknown() {
        const ignored = this.args.ignore ?? [];

        return unknownFields(this.kind, this.value)
            .filter((name) => !ignored.includes(name))
            .map((name) => ({ name, path: this.pathOf(name), value: this.value[name] }));
    }

    pathOf(name) {
        return this.args.path ? `${this.args.path}.${name}` : name;
    }

    @action setField(name, next) {
        this.args.onChange({ ...this.value, [name]: next });
    }

    @action setVariant(event) {
        this.args.onChange(switchVariant(this.args.env.form, this.kind, this.value, event.target.value));
    }

    @action removeUnknown(name) {
        this.args.onChange(withoutKey(this.value, name));
    }
}
