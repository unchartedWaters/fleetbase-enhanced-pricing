import Component from '@glimmer/component';
import { formatValue } from '../../utils/money';

// Subtotal fees and their dated changes (plan, Endpoints): each fee's value today, its upcoming
// changes and the customers whose own value a change won't reach (UX 13), and below, every
// dated change the profile has, of any value, read-only: a subtotal fee's by its name, any other by
// its path.
export default class PricingSubtotalFeesComponent extends Component {
    get changes() {
        const fees = this.args.schedule?.subtotal_fees ?? [];

        return (this.args.schedule?.changes ?? []).map((change) => ({
            ...change,
            name: fees.find((fee) => fee.path === change.path)?.name ?? null,
            shown: formatValue(change.path, change.value, this.args.currency),
        }));
    }
}
