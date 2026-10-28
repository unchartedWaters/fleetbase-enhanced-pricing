import { helper } from '@ember/component/helper';

// A saved time, as the person's browser shows dates and times. The server sends ISO times.
export default helper(function pricingTime([value]) {
    if (!value) {
        return '';
    }
    const date = new Date(value);

    return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
});
