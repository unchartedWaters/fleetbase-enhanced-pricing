import { helper } from '@ember/component/helper';
import { formatMoney } from '../utils/money';

export default helper(function pricingMoney([amount, currency]) {
    return formatMoney(amount, currency);
});
