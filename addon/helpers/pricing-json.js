import { helper } from '@ember/component/helper';

export default helper(function pricingJson([value]) {
    return value === undefined ? '' : JSON.stringify(value);
});
