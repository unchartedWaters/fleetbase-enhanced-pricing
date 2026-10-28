import Component from '@glimmer/component';
import { tracked } from '@glimmer/tracking';
import { action } from '@ember/object';
import { minorToDecimal, decimalToMinor, minorUnits, basisPointsToPercent, percentToBasisPoints } from '../../utils/money';

// A money field typed in @currency's own decimals (whole yen, dollars and cents, dinars and fils),
// or a percent field typed in percent (@kind="percent"). The stored whole number is shown under
// it, so "45" can't pass for 45 cents, nor "31" for 0.31%.
// A value that isn't a plain amount is kept as typed and reported with @onError, not sent.
export default class PricingMoneyInputComponent extends Component {
    @tracked edited = null;
    @tracked error = null;
    sent = undefined;

    get isPercent() {
        return this.args.kind === 'percent';
    }

    // The decimals typed for money; percents always have two.
    get places() {
        return this.isPercent ? 2 : minorUnits(this.args.currency);
    }

    get text() {
        if (this.edited !== null && (this.error || this.sent === this.args.value)) {
            return this.edited;
        }

        return this.isPercent ? basisPointsToPercent(this.args.value) : minorToDecimal(this.args.value, this.args.currency);
    }

    get stored() {
        const value = this.args.value;

        return !this.error && Number.isInteger(value) ? value : null;
    }

    @action input(event) {
        const text = event.target.value;
        const parsed = this.isPercent ? percentToBasisPoints(text) : decimalToMinor(text, this.args.currency);
        this.edited = text;
        if (parsed.error) {
            this.error = parsed.error;
            this.args.onError?.(true);

            return;
        }
        this.error = null;
        this.sent = parsed.value;
        this.args.onError?.(false);
        this.args.onChange?.(parsed.value);
    }

    willDestroy() {
        super.willDestroy(...arguments);
        if (this.error) {
            this.args.onError?.(false);
        }
    }
}
