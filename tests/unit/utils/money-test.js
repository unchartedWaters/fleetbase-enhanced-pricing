import { module, test } from 'qunit';
import {
    centsToDecimal,
    decimalToCents,
    basisPointsToPercent,
    percentToBasisPoints,
    formatMoney,
    formatPercent,
    valueKind,
    formatValue,
} from '@unchartedwaters/enhanced-pricing-engine/utils/money';

// Profiles store money as integer cents and percents as integer basis points. The console shows
// and takes dollars and percents, so "45" can't be saved as 45 cents, nor "31" as 0.31%.
module('Unit | Utility | money', () => {
    test('cents are shown as decimals with two places', (assert) => {
        assert.strictEqual(centsToDecimal(4500), '45.00');
        assert.strictEqual(centsToDecimal(5), '0.05');
        assert.strictEqual(centsToDecimal(0), '0.00');
        assert.strictEqual(centsToDecimal(100000000), '1000000.00');
        assert.strictEqual(centsToDecimal(null), '');
    });

    test('decimals become exact cents', (assert) => {
        assert.deepEqual(decimalToCents('45'), { value: 4500 });
        assert.deepEqual(decimalToCents('45.5'), { value: 4550 });
        assert.deepEqual(decimalToCents('45.55'), { value: 4555 });
        assert.deepEqual(decimalToCents(' 0.07 '), { value: 7 });
        assert.deepEqual(decimalToCents('.5'), { value: 50 });
        assert.deepEqual(decimalToCents('1000000.00'), { value: 100000000 });
        // 0.29 * 100 is 28.999999999999996 in floating point; the conversion never uses floats.
        assert.deepEqual(decimalToCents('0.29'), { value: 29 });
        assert.deepEqual(decimalToCents('19.99'), { value: 1999 });
    });

    test('a blank money field is no value', (assert) => {
        assert.deepEqual(decimalToCents(''), { value: null });
        assert.deepEqual(decimalToCents('   '), { value: null });
    });

    test('money that is not a plain amount is refused, not guessed', (assert) => {
        for (const input of ['45.555', '-1', '1,000', '1e3', 'abc', '$45', '45.', '1.2.3', '0x10']) {
            assert.strictEqual(decimalToCents(input).error, 'enhanced-pricing.console.input.money-invalid', input);
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
        for (const cents of [0, 1, 29, 99, 100, 4555, 100000000]) {
            assert.strictEqual(decimalToCents(centsToDecimal(cents)).value, cents);
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
