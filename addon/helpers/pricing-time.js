import { helper } from '@ember/component/helper';

// A saved time on the person's own clock, as YYYY-MM-DD HH:MM. The server sends ISO times. The
// clock is read from Date itself, not Intl or toLocaleString: the host console's Intl polyfill
// has no timezone data and formats every time as UTC.
function pad(number) {
    return String(number).padStart(2, '0');
}

export function formatTime(value) {
    if (!value) {
        return '';
    }
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return String(value);
    }

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

export default helper(function pricingTime([value]) {
    return formatTime(value);
});
