import { helper } from '@ember/component/helper';
import { formatPercent } from '../utils/money';

export default helper(function pricingPercent([points]) {
    return formatPercent(points);
});
