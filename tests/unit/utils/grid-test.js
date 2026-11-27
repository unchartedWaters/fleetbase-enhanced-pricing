import { module, test } from 'qunit';
import { parseTsv, cellsToValues, bytesToBase64, base64ToBytes } from '@unchartedwaters/enhanced-pricing-engine/utils/grid';

// The grid's pure parts: Excel's clipboard text, a typed row as the server's values, and the
// base64 that carries a file in JSON.
module('Unit | Utility | grid', () => {
    const columns = [
        { key: 'code', type: 'text' },
        { key: 'first', type: 'money' },
        { key: 'included', type: 'integer' },
        { key: 'rate', type: 'number' },
    ];

    test('tab and line separated text becomes rows of cells', (assert) => {
        assert.deepEqual(parseTsv('a\tb\r\nc\td\r\n'), [
            ['a', 'b'],
            ['c', 'd'],
        ]);
        assert.deepEqual(parseTsv('a\tb\nc'), [['a', 'b'], ['c']]);
        assert.deepEqual(parseTsv('one'), [['one']]);
        assert.deepEqual(parseTsv(''), []);
    });

    test('a quoted cell may hold tabs, line breaks and doubled quotes, and a blank cell stays', (assert) => {
        assert.deepEqual(parseTsv('"a\tb"\t"line\nbreak"\t"say ""hi"""\n\t5'), [
            ['a\tb', 'line\nbreak', 'say "hi"'],
            ['', '5'],
        ]);
    });

    test('a blank line in the middle is a blank row', (assert) => {
        assert.deepEqual(parseTsv('a\n\nb'), [['a'], [''], ['b']]);
    });

    test('typed cells become values: money in minor units, integers as numbers, blanks left out', (assert) => {
        const { values, errors } = cellsToValues(columns, { code: ' R1 ', first: '12.50', included: '3', rate: '1.25' }, 'USD');

        assert.deepEqual(values, { code: 'R1', first: 1250, included: 3, rate: '1.25' });
        assert.deepEqual(errors, {});
        assert.deepEqual(cellsToValues(columns, { code: 'R1', first: '', included: '', rate: '' }, 'USD').values, { code: 'R1' });
    });

    test('money is read in the currency decimals', (assert) => {
        assert.strictEqual(cellsToValues(columns, { first: '500' }, 'JPY').values.first, 500);
        assert.ok(cellsToValues(columns, { first: '5.5' }, 'JPY').errors.first);
        assert.strictEqual(cellsToValues(columns, { first: '1.234' }, 'KWD').values.first, 1234);
        assert.ok(cellsToValues(columns, { first: '1.234' }, 'USD').errors.first);
        assert.ok(cellsToValues(columns, { first: '$5' }, 'USD').errors.first);
    });

    test('a value of the wrong type is an error on its cell', (assert) => {
        const { errors } = cellsToValues(columns, { included: '2.5', rate: 'abc' }, 'USD');

        assert.ok(errors.included);
        assert.ok(errors.rate);
    });

    test('bytes survive base64, including a large file', (assert) => {
        const bytes = new Uint8Array(300000).map((_, i) => (i * 7) % 256);

        assert.deepEqual(base64ToBytes(bytesToBase64(bytes)), bytes);
        assert.strictEqual(bytesToBase64(new TextEncoder().encode('code,first\n')), 'Y29kZSxmaXJzdAo=');
    });
});
