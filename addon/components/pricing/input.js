import Component from '@glimmer/component';
import { tracked } from '@glimmer/tracking';
import { action } from '@ember/object';
import { guidFor } from '@ember/object/internals';

// One value of a profile, edited in the control its type calls for (Admin/ProfileForm). Money and
// percents use Pricing::MoneyInput. An integer that isn't a whole number is kept as typed and
// reported with @onError, not sent.
const TEXT = new Set(['key', 'code', 'text', 'number']);
const WEEKDAYS = [1, 2, 3, 4, 5, 6, 7];

// Currency and timezone are typed into a search box over the browser's own lists (Intl), so no
// list is shipped. The server still checks the value (an ISO code, an IANA name). shortcut: a
// browser without Intl.supportedValuesOf gets a plain text box, which the server checks.
function supported(kind) {
    try {
        return Intl.supportedValuesOf(kind);
    } catch {
        return [];
    }
}

const lists = {};

function currencyOptions() {
    lists.currency ??= (() => {
        let names = null;
        try {
            names = new Intl.DisplayNames(['en'], { type: 'currency' });
        } catch {
            // no names: the codes alone are listed
        }

        return supported('currency')
            .filter((code) => /^[A-Z]{3}$/.test(code))
            .map((code) => ({ value: code, label: names?.of(code) ?? code }));
    })();

    return lists.currency;
}

function timezoneOptions() {
    lists.timezone ??= [...new Set([...supported('timeZone'), 'UTC'])].map((value) => ({ value, label: value.replace(/_/g, ' ') }));

    return lists.timezone;
}

export default class PricingInputComponent extends Component {
    @tracked integerText = null;
    @tracked integerError = false;
    @tracked listWarning = false;
    sent = undefined;
    listId = `pricing-list-${guidFor(this)}`;

    get type() {
        return this.args.type;
    }

    get isText() {
        return TEXT.has(this.type);
    }

    get isListed() {
        return this.type === 'currency' || this.type === 'timezone';
    }

    get listOptions() {
        return this.type === 'currency' ? currencyOptions() : timezoneOptions();
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

    // The browser's list can lack a value the server accepts (an alias such as Etc/UTC, or a newer
    // zone), so an unlisted value is warned about, not blocked; the server has the last word.
    @action pick(event) {
        const typed = event.target.value.trim();
        const text = this.type === 'currency' ? typed.toUpperCase() : typed;
        const listed = this.listOptions.find((option) => option.value.toLowerCase() === text.toLowerCase());
        const value = listed?.value ?? text;
        this.listWarning = text !== '' && listed === undefined && this.listOptions.length > 0;
        event.target.value = value;
        this.args.onChange(value === '' && this.args.nullable ? null : value);
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
