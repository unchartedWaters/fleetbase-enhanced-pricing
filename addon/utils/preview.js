// The quote preview takes a pickup in the order's own timezone and a distance in the profile's
// unit. The server takes the pickup as typed, with no offset, and reads it in the order's timezone
// (Admin/PreviewInput), and the distance in whole meters. The browser doesn't convert the time:
// the host console's Intl polyfill formats every timezone as UTC.

const DATE = /^(\d{4})-(\d{2})-(\d{2})$/;
const TIME = /^([01]\d|2[0-3]):[0-5]\d$/;

// A date and time as typed, or null when either is blank or the date doesn't exist.
export function localPickup(date, time) {
    const day = DATE.exec(date ?? '');
    if (!day || !TIME.test(time ?? '')) {
        return null;
    }
    const [year, month, dayOfMonth] = day.slice(1).map(Number);
    const check = new Date(Date.UTC(year, month - 1, dayOfMonth));
    if (check.getUTCFullYear() !== year || check.getUTCMonth() !== month - 1 || check.getUTCDate() !== dayOfMonth) {
        return null;
    }

    return `${date}T${time}`;
}

const METERS_PER_MILE = 1609.344;

export function distanceToMeters(input, unit) {
    const error = 'enhanced-pricing.console.input.distance-invalid';
    const text = String(input ?? '').trim();
    if (text === '') {
        return { value: null };
    }
    if (unit === 'mi') {
        if (!/^(\d+(\.\d+)?|\.\d+)$/.test(text)) {
            return { error };
        }
        const meters = Math.round(Number(text) * METERS_PER_MILE);

        return Number.isSafeInteger(meters) ? { value: meters } : { error };
    }
    const match = /^(\d*)(?:\.(\d{1,3}))?$/.exec(text);
    if (!match || (match[1] === '' && match[2] === undefined)) {
        return { error };
    }
    const meters = Number(`${match[1] || '0'}${(match[2] ?? '').padEnd(3, '0')}`);

    return Number.isSafeInteger(meters) ? { value: meters } : { error };
}
