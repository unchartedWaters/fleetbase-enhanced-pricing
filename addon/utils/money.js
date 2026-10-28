// Profiles store money as an integer count of the currency's minor unit (cents for USD, whole yen
// for JPY, fils for KWD) and percents as integer basis points (docs/profile-format.md,
// Conventions). Staff type amounts and percents. Every conversion here is string arithmetic, so a
// value is never rounded by floating point: 0.29 is 29 cents, not 28.999….

// ISO 4217 minor units that aren't 2, as moneyphp (which Fleetbase's server formats money with)
// uses them. Intl's own defaults come from CLDR and differ for some codes (it shows IQD and
// RSD with no decimals), so they aren't used to read or write a stored amount.
const MINOR_UNITS = {
    BIF: 0,
    CLP: 0,
    DJF: 0,
    GNF: 0,
    ISK: 0,
    JPY: 0,
    KMF: 0,
    KRW: 0,
    PYG: 0,
    RWF: 0,
    UGX: 0,
    UYI: 0,
    VND: 0,
    VUV: 0,
    XAF: 0,
    XOF: 0,
    XPF: 0,
    BHD: 3,
    IQD: 3,
    JOD: 3,
    KWD: 3,
    LYD: 3,
    OMR: 3,
    TND: 3,
    CLF: 4,
    UYW: 4,
};

// The number of decimals in a currency's minor unit: 0 for JPY, 2 for USD, 3 for KWD. An unknown
// or missing code has 2.
export function minorUnits(currency) {
    const code = String(currency ?? '').toUpperCase();

    return Object.prototype.hasOwnProperty.call(MINOR_UNITS, code) ? MINOR_UNITS[code] : 2;
}

function decimalPattern(places) {
    return places === 0 ? /^(\d+)()$/ : new RegExp(`^(\\d*)(?:\\.(\\d{1,${places}}))?$`);
}

function digitsToInteger(whole, fraction, places) {
    const digits = `${whole || '0'}${(fraction ?? '').padEnd(places, '0')}`.replace(/^0+(?=\d)/, '');

    return Number(digits);
}

function parseScaled(input, places, error) {
    const text = String(input ?? '').trim();
    if (text === '') {
        return { value: null };
    }
    const match = decimalPattern(places).exec(text);
    if (match === null || (match[1] === '' && match[2] === undefined)) {
        return { error };
    }
    const value = digitsToInteger(match[1], match[2], places);

    return Number.isSafeInteger(value) ? { value } : { error };
}

function scaledToText(value, places, keepPlaces) {
    if (value === null || value === undefined || value === '') {
        return '';
    }
    const digits = String(Math.trunc(Number(value))).padStart(places + 1, '0');
    if (places === 0) {
        return digits;
    }
    const whole = digits.slice(0, -places).replace(/^0+(?=\d)/, '');
    const fraction = digits.slice(-places);
    if (keepPlaces) {
        return `${whole}.${fraction}`;
    }
    const trimmed = fraction.replace(/0+$/, '');

    return trimmed === '' ? whole : `${whole}.${trimmed}`;
}

export function minorToDecimal(amount, currency) {
    return scaledToText(amount, minorUnits(currency), true);
}

export function decimalToMinor(input, currency) {
    return parseScaled(input, minorUnits(currency), 'enhanced-pricing.console.input.money-invalid');
}

export function basisPointsToPercent(points) {
    return scaledToText(points, 2, false);
}

export function percentToBasisPoints(input) {
    return parseScaled(input, 2, 'enhanced-pricing.console.input.percent-invalid');
}

export function formatMoney(amount, currency) {
    if (amount === null || amount === undefined || amount === '') {
        return '';
    }
    const places = minorUnits(currency);
    const decimal = scaledToText(amount, places, true);
    try {
        return new Intl.NumberFormat('en-US', { style: 'currency', currency, minimumFractionDigits: places, maximumFractionDigits: places }).format(decimal);
    } catch {
        return `${decimal} ${currency ?? ''}`.trim();
    }
}

export function formatPercent(points) {
    const text = basisPointsToPercent(points);

    return text === '' ? '' : `${text}%`;
}

// What an override or dated change path holds, by its last field: subtotal_fees.K.percentage is
// basis points; an amount, minimum, maximum, rate or base fee is money in minor units. Anything else (a list,
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
// tier's price ({value: amount}), shows each amount in its unit too, so no amount is shown bare.
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
