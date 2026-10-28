// The quote preview takes a pickup in the order's own timezone and a distance in the profile's
// unit. The server takes an ISO time with an offset and whole meters (Admin/PreviewInput).

const DATE = /^(\d{4})-(\d{2})-(\d{2})$/;
const TIME = /^(\d{2}):(\d{2})$/;

function offsetAt(time, zone) {
    const parts = {};
    const format = new Intl.DateTimeFormat('en-US', {
        timeZone: zone,
        hourCycle: 'h23',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
    for (const part of format.formatToParts(new Date(time))) {
        parts[part.type] = Number(part.value);
    }
    const local = Date.UTC(parts.year, parts.month - 1, parts.day, parts.hour, parts.minute, parts.second);

    return local - Math.floor(time / 1000) * 1000;
}

// A wall-clock time in a timezone as UTC. The offset is looked up twice so a time near a
// daylight saving change uses the offset in force at that time.
export function zonedToUtc(date, time, zone) {
    const day = DATE.exec(date ?? '');
    const clock = TIME.exec(time ?? '');
    if (!day || !clock) {
        return null;
    }
    const [year, month, dayOfMonth] = day.slice(1).map(Number);
    const [hour, minute] = clock.slice(1).map(Number);
    const guess = Date.UTC(year, month - 1, dayOfMonth, hour, minute);
    const check = new Date(guess);
    if (check.getUTCMonth() !== month - 1 || check.getUTCDate() !== dayOfMonth || hour > 23 || minute > 59) {
        return null;
    }
    try {
        let utc = guess - offsetAt(guess, zone);
        const second = offsetAt(utc, zone);
        utc = guess - second;

        return new Date(utc).toISOString().replace('.000Z', 'Z');
    } catch {
        return null;
    }
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
