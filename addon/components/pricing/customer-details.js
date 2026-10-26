import Component from '@glimmer/component';
import { formatMoney, formatValue } from '../../utils/money';

// One customer of a profile, read-only: overrides and cards change only through a loader (plan).
// Each override is shown in its unit next to the profile's own value and the dated change in
// effect, and each card's rows with money columns in the profile's currency.
export default class PricingCustomerDetailsComponent extends Component {
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
