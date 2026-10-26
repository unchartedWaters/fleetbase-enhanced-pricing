import Component from '@glimmer/component';
import { tracked } from '@glimmer/tracking';
import { action } from '@ember/object';
import { formatMoney, formatPercent } from '../../utils/money';

// One subtotal fee: its value today and upcoming changes, and a form to schedule a change to its
// global percentage or amount. A change much larger or smaller than today's value (UX 14) is
// refused until the person confirms it.
export default class PricingSubtotalFeeComponent extends Component {
    @tracked value = null;
    @tracked date = '';
    @tracked inputError = false;
    @tracked problems = [];
    @tracked large = null;
    @tracked failure = null;
    @tracked sending = false;
    @tracked confirmingCancel = null;

    get isPercent() {
        return this.args.fee.mode === 'percentage';
    }

    show(value) {
        if (value === null || value === undefined) {
            return '';
        }

        return this.isPercent ? formatPercent(value) : formatMoney(value, this.args.currency);
    }

    get current() {
        return this.show(this.args.fee.current?.value);
    }

    get upcoming() {
        return (this.args.fee.upcoming ?? []).map((change) => ({ ...change, shown: this.show(change.value) }));
    }

    get overridden() {
        return (this.args.fee.overridden_by ?? []).map((customer) => ({ ...customer, shown: this.show(customer.value) }));
    }

    get cannotSchedule() {
        return this.sending || this.inputError || this.value === null || this.date === '';
    }

    @action setValue(value) {
        this.value = value;
        this.large = null;
    }

    @action setError(failing) {
        this.inputError = failing;
    }

    @action setDate(event) {
        this.date = event.target.value;
        this.large = null;
    }

    @action schedule() {
        return this.send(false);
    }

    @action confirmLarge() {
        return this.send(true);
    }

    async send(acceptLarge) {
        this.sending = true;
        this.problems = [];
        this.failure = null;
        try {
            await this.args.onSchedule({ path: this.args.fee.path, value: this.value, effective_from: this.date, accept_large_change: acceptLarge });
            this.large = null;
            this.value = null;
            this.date = '';
        } catch (refusal) {
            if (refusal?.refused === 'large-change') {
                this.large = refusal.problems ?? [];
            } else if (refusal?.refused === 'invalid') {
                this.large = null;
                this.problems = refusal.problems ?? [];
            } else {
                this.large = null;
                this.failure = refusal ?? {};
            }
        } finally {
            if (!this.isDestroying && !this.isDestroyed) {
                this.sending = false;
            }
        }
    }

    @action askCancel(uuid) {
        this.confirmingCancel = uuid;
    }

    @action async cancel(uuid) {
        this.confirmingCancel = null;
        this.failure = null;
        try {
            await this.args.onCancel(uuid);
        } catch (refusal) {
            this.failure = refusal ?? {};
        }
    }
}
