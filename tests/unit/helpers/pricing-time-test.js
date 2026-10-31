import { module, test } from 'qunit';
import { formatTime } from '@unchartedwaters/enhanced-pricing-engine/helpers/pricing-time';

// A saved time is shown on the person's own clock. The host console's Intl polyfill formats
// every time as UTC, toLocaleString included, so the helper reads the clock from Date itself.
module('Unit | Helper | pricing-time', () => {
    function local(date) {
        const pad = (number) => String(number).padStart(2, '0');

        return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}`;
    }

    test("a saved time is shown on the browser's clock, without Intl", (assert) => {
        const native = Date.prototype.toLocaleString;
        Date.prototype.toLocaleString = () => 'formatted as UTC';
        try {
            const iso = '2026-10-29T16:05:00+00:00';
            assert.strictEqual(formatTime(iso), local(new Date(iso)));
        } finally {
            Date.prototype.toLocaleString = native;
        }
    });

    test('nothing, or text that is not a time, is shown as it is', (assert) => {
        assert.strictEqual(formatTime(null), '');
        assert.strictEqual(formatTime('soon'), 'soon');
    });
});
