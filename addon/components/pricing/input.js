import Component from '@glimmer/component';
import { tracked } from '@glimmer/tracking';
import { action } from '@ember/object';

// One value of a profile, edited in the control its type calls for (Admin/ProfileForm). Money and
// percents use Pricing::MoneyInput. An integer that isn't a whole number is kept as typed and
// reported with @onError, not sent.
const TEXT = new Set(['key', 'code', 'text', 'timezone', 'currency', 'number']);
const WEEKDAYS = [1, 2, 3, 4, 5, 6, 7];

export default class PricingInputComponent extends Component {
    @tracked integerText = null;
    @tracked integerError = false;
    sent = undefined;

    get type() {
        return this.args.type;
    }

    get isText() {
        return TEXT.has(this.type);
    }

    get isMoney() {
        return this.type === 'money' || this.type === 'percent';
    }

    get blankable() {
        return this.args.nullable || this.args.optional;
    }

    get isBlank() {
        return this.args.value === null || this.args.value === undefined || this.args.value === '';
    }

    get textValue() {
        const value = this.args.value;

        return value === null || value === undefined ? '' : String(value);
    }

    // Typed text is kept while it is wrong or is still the value, as MoneyInput does.
    get integerValue() {
        if (this.integerText !== null && (this.integerError || this.sent === this.args.value)) {
            return this.integerText;
        }

        return this.textValue;
    }

    // A select keeps a value the profile doesn't hold, so it is shown, not silently dropped.
    get selectOptions() {
        const options = (this.args.options ?? []).map((option) => (typeof option === 'object' ? option : { value: option, label: option }));
        const value = this.args.value;
        if (value !== null && value !== undefined && value !== '' && !options.some((option) => option.value === value)) {
            options.push({ value, label: String(value), missing: true });
        }

        return options;
    }

    get refsOptions() {
        const chosen = Array.isArray(this.args.value) ? this.args.value : [];
        const options = (this.args.options ?? []).map((option) => ({ ...option, checked: chosen.includes(option.value) }));
        for (const value of chosen) {
            if (!options.some((option) => option.value === value)) {
                options.push({ value, label: String(value), checked: true, missing: true });
            }
        }

        return options;
    }

    get weekdays() {
        const chosen = Array.isArray(this.args.value) ? this.args.value : [];

        return WEEKDAYS.map((day) => ({ day, checked: chosen.includes(day) }));
    }

    get datesText() {
        return Array.isArray(this.args.value) ? this.args.value.join('\n') : '';
    }

    @action text(event) {
        const text = event.target.value;
        this.args.onChange(text === '' && this.args.nullable ? null : text);
    }

    @action blankToNull(event) {
        const text = event.target.value;
        this.args.onChange(text === '' ? null : text);
    }

    @action integer(event) {
        const text = event.target.value.trim();
        this.integerText = text;
        if (text === '') {
            this.integerError = false;
            this.sent = null;
            this.args.onError?.(false);
            this.args.onChange(null);
        } else if (/^\d+$/.test(text) && Number.isSafeInteger(Number(text))) {
            this.integerError = false;
            this.sent = Number(text);
            this.args.onError?.(false);
            this.args.onChange(this.sent);
        } else {
            this.integerError = true;
            this.args.onError?.(true);
        }
    }

    @action check(event) {
        this.args.onChange(event.target.checked);
    }

    @action choose(event) {
        const text = event.target.value;
        this.args.onChange(text === '' ? null : text);
    }

    @action toggle(value, event) {
        const chosen = Array.isArray(this.args.value) ? [...this.args.value] : [];
        const next = event.target.checked ? [...chosen, value] : chosen.filter((item) => item !== value);
        this.args.onChange(next);
    }

    @action toggleDay(day, event) {
        const chosen = Array.isArray(this.args.value) ? this.args.value : [];
        const next = (event.target.checked ? [...chosen, day] : chosen.filter((item) => item !== day)).sort((a, b) => a - b);
        this.args.onChange(next.length === 0 && this.blankable ? null : next);
    }

    @action dates(event) {
        const dates = event.target.value
            .split(/[\s,]+/)
            .map((date) => date.trim())
            .filter(Boolean);
        this.args.onChange(dates);
    }

    willDestroy() {
        super.willDestroy(...arguments);
        if (this.integerError) {
            this.args.onError?.(false);
        }
    }
}
