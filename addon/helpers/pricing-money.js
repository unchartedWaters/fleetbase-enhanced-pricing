import { helper } from '@ember/component/helper';
import { formatMoney } from '../utils/money';

export default helper(function pricingMoney([cents, currency]) {
    return formatMoney(cents, currency);
});
