import Component from '@glimmer/component';
import { tracked } from '@glimmer/tracking';
import { action } from '@ember/object';
import { inject as service } from '@ember/service';
import { formatMoney, formatValue } from '../../utils/money';

// One customer of a profile. Overrides are read-only (they change only through a loader); each is
// shown in its unit next to the profile's own value and the dated change in effect. Each card's
// rows are shown with money columns in the profile's currency, and edited in a grid per rate table.
export default class PricingCustomerDetailsComponent extends Component {
    @service pricingApi;

    @tracked newCard = '';
    @tracked cardProblems = [];

    get tables() {
        return this.args.customer?.profile?.tables ?? [];
    }

    // A card is made by saving its first table with no rows; the grids then fill it.
    @action async addCard() {
        const label = this.newCard.trim();
        if (label === '' || this.tables.length === 0) {
            return;
        }
        this.cardProblems = [];
        try {
            await this.pricingApi.put(`admin/rate-cards/${this.pricingApi.segment(this.args.customer.uuid)}/rows`, { card: label, table: this.tables[0].key, rows: [], replace: true });
            this.newCard = '';
            await this.args.onChanged?.();
        } catch (refusal) {
            this.cardProblems = refusal.problems ?? [];
        }
    }

    @action typeCard(event) {
        this.newCard = event.target.value;
    }

    get currency() {
        return this.args.customer?.profile?.currency;
    }

    get overrides() {
        return (this.args.customer?.overrides ?? []).map((override) => ({
            path: override.path,
            value: formatValue(override.path, override.value, this.currency),
            default: override.default === null || override.default === undefined ? null : formatValue(override.path, override.default, this.currency),
            change: override.change ? { value: formatValue(override.path, override.change.value, this.currency), from: override.change.effective_from } : null,
        }));
    }

    get cards() {
        const columns = this.args.customer?.profile?.columns ?? {};

        return (this.args.customer?.cards ?? []).map((card) => ({
            ...card,
            rows: card.rows.map((row) => ({
                ...row,
                key: row.key_values.join(' · '),
                cells: Object.entries(row.values ?? {}).map(([column, value]) => ({
                    column,
                    value: columns[row.table_key]?.[column] === 'money' && Number.isInteger(value) ? formatMoney(value, this.currency) : value === null ? '' : String(value),
                })),
            })),
        }));
    }
}
