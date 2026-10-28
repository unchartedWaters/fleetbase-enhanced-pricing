import Component from '@glimmer/component';
import { formatValue } from '../../utils/money';

// Subtotal fees and their dated changes (plan, Endpoints): each fee's value today, its upcoming
// changes and the customers whose own value a change won't reach (UX 13), and below, every
// dated change the profile has, of any value, read-only.
export default class PricingSubtotalFeesComponent extends Component {
    get changes() {
        return (this.args.schedule?.changes ?? []).map((change) => ({
            ...change,
            shown: formatValue(change.path, change.value, this.args.currency),
        }));
    }
}
