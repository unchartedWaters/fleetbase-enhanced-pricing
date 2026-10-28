import { module, test } from 'qunit';
import {
    minorUnits,
    minorToDecimal,
    decimalToMinor,
    basisPointsToPercent,
    percentToBasisPoints,
    formatMoney,
    formatPercent,
    valueKind,
    formatValue,
} from '@unchartedwaters/enhanced-pricing-engine/utils/money';

// Profiles store money as an integer count of the currency's minor unit (cents for USD, yen for
// JPY, fils for KWD) and percents as integer basis points. The console shows and takes amounts
// and percents, so "45" can't be saved as 45 cents, nor "31" as 0.31%.
module('Unit | Utility | money', () => {
    test("a currency's minor unit is its ISO 4217 exponent, not the browser's display default", (assert) => {
        assert.strictEqual(minorUnits('USD'), 2);
        assert.strictEqual(minorUnits('CAD'), 2);
        assert.strictEqual(minorUnits('JPY'), 0);
        assert.strictEqual(minorUnits('KRW'), 0);
        assert.strictEqual(minorUnits('KWD'), 3);
        assert.strictEqual(minorUnits('BHD'), 3);
        assert.strictEqual(minorUnits('CLF'), 4);
        // Intl shows these with no decimals, but ISO 4217 (and so moneyphp) gives them 3
        // and 2, so a stored amount can't depend on the browser's locale data.
        assert.strictEqual(minorUnits('IQD'), 3);
        assert.strictEqual(minorUnits('RSD'), 2);
        assert.strictEqual(minorUnits('HUF'), 2);
        // An unknown code or none keeps the old two places.
        assert.strictEqual(minorUnits('ZZZ'), 2);
        assert.strictEqual(minorUnits(undefined), 2);
        assert.strictEqual(minorUnits('jpy'), 0);
    });

    test('cents are shown as decimals with two places', (assert) => {
        assert.strictEqual(minorToDecimal(4500, 'USD'), '45.00');
        assert.strictEqual(minorToDecimal(5, 'USD'), '0.05');
        assert.strictEqual(minorToDecimal(0, 'USD'), '0.00');
        assert.strictEqual(minorToDecimal(100000000, 'USD'), '1000000.00');
        assert.strictEqual(minorToDecimal(null, 'USD'), '');
    });

    test('a zero-decimal amount is shown whole and a three-decimal one with three places', (assert) => {
        assert.strictEqual(minorToDecimal(4500, 'JPY'), '4500');
        assert.strictEqual(minorToDecimal(5, 'JPY'), '5');
        assert.strictEqual(minorToDecimal(0, 'JPY'), '0');
        assert.strictEqual(minorToDecimal(100000000, 'JPY'), '100000000');
        assert.strictEqual(minorToDecimal(1234, 'KWD'), '1.234');
        assert.strictEqual(minorToDecimal(5, 'KWD'), '0.005');
        assert.strictEqual(minorToDecimal(0, 'KWD'), '0.000');
        assert.strictEqual(minorToDecimal(12345, 'CLF'), '1.2345');
    });

    test('decimals become exact cents', (assert) => {
        assert.deepEqual(decimalToMinor('45', 'USD'), { value: 4500 });
        assert.deepEqual(decimalToMinor('45.5', 'USD'), { value: 4550 });
        assert.deepEqual(decimalToMinor('45.55', 'USD'), { value: 4555 });
        assert.deepEqual(decimalToMinor(' 0.07 ', 'USD'), { value: 7 });
        assert.deepEqual(decimalToMinor('.5', 'USD'), { value: 50 });
        assert.deepEqual(decimalToMinor('1000000.00', 'USD'), { value: 100000000 });
        // 0.29 * 100 is 28.999999999999996 in floating point; the conversion never uses floats.
        assert.deepEqual(decimalToMinor('0.29', 'USD'), { value: 29 });
        assert.deepEqual(decimalToMinor('19.99', 'USD'), { value: 1999 });
    });

    test('a zero-decimal amount is taken whole and a three-decimal one to the thousandth', (assert) => {
        assert.deepEqual(decimalToMinor('4500', 'JPY'), { value: 4500 });
        assert.deepEqual(decimalToMinor('045', 'JPY'), { value: 45 });
        assert.deepEqual(decimalToMinor('1.234', 'KWD'), { value: 1234 });
        assert.deepEqual(decimalToMinor('1.2', 'KWD'), { value: 1200 });
        assert.deepEqual(decimalToMinor('.005', 'KWD'), { value: 5 });
        assert.deepEqual(decimalToMinor('45', 'KWD'), { value: 45000 });
    });

    test('a blank money field is no value', (assert) => {
        assert.deepEqual(decimalToMinor('', 'USD'), { value: null });
        assert.deepEqual(decimalToMinor('   ', 'JPY'), { value: null });
    });

    test('money that is not a plain amount is refused, not guessed', (assert) => {
        for (const input of ['45.555', '-1', '1,000', '1e3', 'abc', '$45', '45.', '1.2.3', '0x10']) {
            assert.strictEqual(decimalToMinor(input, 'USD').error, 'enhanced-pricing.console.input.money-invalid', input);
        }
    });

    test('more decimals than the currency has are refused, not rounded', (assert) => {
        for (const input of ['45.5', '45.00', '.5', '0.1']) {
            assert.strictEqual(decimalToMinor(input, 'JPY').error, 'enhanced-pricing.console.input.money-invalid', input);
        }
        for (const input of ['1.2345', '0.0001']) {
            assert.strictEqual(decimalToMinor(input, 'KWD').error, 'enhanced-pricing.console.input.money-invalid', input);
        }
    });

    test('basis points are shown as percents', (assert) => {
        assert.strictEqual(basisPointsToPercent(1250), '12.5');
        assert.strictEqual(basisPointsToPercent(3100), '31');
        assert.strictEqual(basisPointsToPercent(31), '0.31');
        assert.strictEqual(basisPointsToPercent(5), '0.05');
        assert.strictEqual(basisPointsToPercent(0), '0');
        assert.strictEqual(basisPointsToPercent(null), '');
    });

    test('percents become exact basis points', (assert) => {
        assert.deepEqual(percentToBasisPoints('31'), { value: 3100 });
        assert.deepEqual(percentToBasisPoints('12.5'), { value: 1250 });
        assert.deepEqual(percentToBasisPoints('0.31'), { value: 31 });
        assert.deepEqual(percentToBasisPoints('0.29'), { value: 29 });
        assert.deepEqual(percentToBasisPoints(''), { value: null });
    });

    test('percents with more than two decimals or a sign are refused', (assert) => {
        for (const input of ['12.555', '-5', '5%', 'ten', '1e2']) {
            assert.strictEqual(percentToBasisPoints(input).error, 'enhanced-pricing.console.input.percent-invalid', input);
        }
    });

    test('conversion round-trips', (assert) => {
        for (const currency of ['USD', 'JPY', 'KWD', 'CLF']) {
            for (const amount of [0, 1, 29, 99, 100, 4555, 100000000]) {
                assert.strictEqual(decimalToMinor(minorToDecimal(amount, currency), currency).value, amount, `${amount} ${currency}`);
            }
        }
        for (const points of [0, 1, 29, 31, 1250, 3100, 100000]) {
            assert.strictEqual(percentToBasisPoints(basisPointsToPercent(points)).value, points);
        }
    });

    test('formatted money uses the profile currency', (assert) => {
        assert.strictEqual(formatMoney(4500, 'CAD'), 'CA$45.00');
        assert.strictEqual(formatMoney(4500, 'USD'), '$45.00');
        assert.strictEqual(formatMoney(null, 'USD'), '');
        assert.strictEqual(formatPercent(1250), '12.5%');
    });

    test("formatted money uses the currency's own decimals", (assert) => {
        assert.strictEqual(formatMoney(4500, 'JPY'), '¥4,500');
        assert.strictEqual(formatMoney(1234, 'KWD'), 'KWD\u00a01.234');
        assert.strictEqual(formatMoney(5, 'KWD'), 'KWD\u00a00.005');
        // Intl would show IQD with no decimals and so hide the fils that are stored.
        assert.strictEqual(formatMoney(1234, 'IQD'), 'IQD\u00a01.234');
        // A code Intl doesn't know is shown with two places and its code.
        assert.strictEqual(formatMoney(4500, 'ZZZ'), 'ZZZ\u00a045.00');
    });

    test('an override in a zero-decimal currency is shown whole', (assert) => {
        assert.strictEqual(formatValue('fees.heavy.minimum', 500, 'JPY'), '¥500');
        assert.strictEqual(formatValue('service_rates.flat.fixed_rate.amount', { value: 1250 }, 'JPY'), '{value: ¥1,250}');
        assert.strictEqual(formatValue('subtotal_fees.percent.percentage', 900, 'JPY'), '9%');
    });

    test('an override path is shown in its unit', (assert) => {
        assert.strictEqual(valueKind('subtotal_fees.percent.percentage'), 'percent');
        assert.strictEqual(valueKind('fees.heavy.minimum'), 'money');
        assert.strictEqual(valueKind('service_rates.distance.per_meter.rate'), 'money');
        assert.strictEqual(valueKind('service_rates.distance.per_meter.minimum_distance'), null);
        assert.strictEqual(formatValue('subtotal_fees.percent.percentage', 900, 'USD'), '9%');
        assert.strictEqual(formatValue('fees.heavy.minimum', 500, 'USD'), '$5.00');
        assert.strictEqual(formatValue('allowed_service_rates', ['tiered', 'distance'], 'USD'), '[tiered, distance]');
        assert.strictEqual(formatValue('service_rates.tiered.enabled', false, 'USD'), 'false');
        assert.strictEqual(formatValue('fees.heavy.minimum', null, 'USD'), '');
    });

    test('money and percents inside a list or object are shown in their unit too', (assert) => {
        assert.strictEqual(formatValue('fees.heavy.thresholds', [{ over: 30, amount: 40 }], 'USD'), '[{over: 30, amount: $0.40}]');
        assert.strictEqual(formatValue('service_rates.tiered.tiers', [{ up_to: { value: 5 }, price: { value: 4500 } }], 'USD'), '[{up_to: {value: 5}, price: {value: $45.00}}]');
        assert.strictEqual(formatValue('service_rates.tiered.tiers', [{ up_to: null, price: { column: 'first' } }], 'USD'), '[{up_to: null, price: {column: first}}]');
        assert.strictEqual(formatValue('service_rates.flat.fixed_rate.amount', { value: 1250 }, 'USD'), '{value: $12.50}');
        assert.strictEqual(formatValue('service_rates.flat.fixed_rate.amount', { column: 'first' }, 'USD'), '{column: first}');
        assert.strictEqual(formatValue('manual_charges.entered.entered', { minimum: 800, maximum: 15000 }, 'USD'), '{minimum: $8.00, maximum: $150.00}');
    });
});
