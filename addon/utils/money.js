// Profiles store money as integer cents and percents as integer basis points (docs/profile-format.md,
// Conventions). Staff type dollars and percents. Every conversion here is string arithmetic, so a
// value is never rounded by floating point: 0.29 is 29 cents, not 28.999….

const DECIMAL = /^(\d*)(?:\.(\d{1,2}))?$/;

function digitsToInteger(whole, fraction) {
    const cents = `${whole || '0'}${(fraction ?? '').padEnd(2, '0')}`.replace(/^0+(?=\d)/, '');

    return Number(cents);
}

function parseHundredths(input, error) {
    const text = String(input ?? '').trim();
    if (text === '') {
        return { value: null };
    }
    const match = DECIMAL.exec(text);
    if (match === null || (match[1] === '' && match[2] === undefined)) {
        return { error };
    }
    const value = digitsToInteger(match[1], match[2]);

    return Number.isSafeInteger(value) ? { value } : { error };
}

function hundredthsToText(value, keepPlaces) {
    if (value === null || value === undefined || value === '') {
        return '';
    }
    const digits = String(Math.trunc(Number(value))).padStart(3, '0');
    const whole = digits.slice(0, -2).replace(/^0+(?=\d)/, '');
    const fraction = digits.slice(-2);
    if (keepPlaces) {
        return `${whole}.${fraction}`;
    }
    const trimmed = fraction.replace(/0+$/, '');

    return trimmed === '' ? whole : `${whole}.${trimmed}`;
}

export function centsToDecimal(cents) {
    return hundredthsToText(cents, true);
}

export function decimalToCents(input) {
    return parseHundredths(input, 'enhanced-pricing.console.input.money-invalid');
}

export function basisPointsToPercent(points) {
    return hundredthsToText(points, false);
}

export function percentToBasisPoints(input) {
    return parseHundredths(input, 'enhanced-pricing.console.input.percent-invalid');
}

export function formatMoney(cents, currency) {
    if (cents === null || cents === undefined || cents === '') {
        return '';
    }
    const decimal = centsToDecimal(cents);
    try {
        return new Intl.NumberFormat('en-US', { style: 'currency', currency, minimumFractionDigits: 2 }).format(decimal);
    } catch {
        return `${decimal} ${currency ?? ''}`.trim();
    }
}

export function formatPercent(points) {
    const text = basisPointsToPercent(points);

    return text === '' ? '' : `${text}%`;
}

// What an override or dated change path holds, by its last field: subtotal_fees.K.percentage is
// basis points; an amount, minimum, maximum, rate or base fee is cents. Anything else (a list,
// an object, a number or a flag) is shown as it is stored.
const MONEY_FIELDS = new Set(['amount', 'minimum', 'maximum', 'rate', 'base_fee', 'price']);

export function valueKind(path) {
    const field = String(path ?? '')
        .split('.')
        .pop();
    if (field === 'percentage') {
        return 'percent';
    }

    return MONEY_FIELDS.has(field) ? 'money' : null;
}

// An override's value in its unit. A list or object, such as a parcel fee's thresholds or a
// tier's price ({value: cents}), shows each amount in its unit too, so no cents are shown bare.
export function formatValue(path, value, currency) {
    if (value === null || value === undefined) {
        return '';
    }

    return formatIn(valueKind(path), value, currency);
}

function formatIn(kind, value, currency) {
    if (Array.isArray(value)) {
        return `[${value.map((item) => formatIn(kind, item, currency)).join(', ')}]`;
    }
    if (value !== null && typeof value === 'object') {
        const fields = Object.entries(value).map(([name, item]) => `${name}: ${formatIn(name === 'value' ? kind : valueKind(name), item, currency)}`);

        return `{${fields.join(', ')}}`;
    }
    if (Number.isInteger(value) && kind === 'percent') {
        return formatPercent(value);
    }
    if (Number.isInteger(value) && kind === 'money') {
        return formatMoney(value, currency);
    }

    return String(value);
}
