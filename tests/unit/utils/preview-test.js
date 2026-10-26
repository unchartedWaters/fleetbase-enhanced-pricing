import { module, test } from 'qunit';
import { zonedToUtc, distanceToMeters } from '@unchartedwaters/enhanced-pricing-engine/utils/preview';

// The preview takes a pickup in the order's own timezone and a distance in the profile's unit,
// and sends what the server takes: an ISO time with an offset, and whole meters.
module('Unit | Utility | preview', () => {
    test('a pickup time is read in its timezone', (assert) => {
        assert.strictEqual(zonedToUtc('2027-03-06', '11:00', 'UTC'), '2027-03-06T11:00:00Z');
        assert.strictEqual(zonedToUtc('2027-03-06', '11:00', 'America/Edmonton'), '2027-03-06T18:00:00Z', 'MST is UTC-7');
        assert.strictEqual(zonedToUtc('2027-07-06', '11:00', 'America/Edmonton'), '2027-07-06T17:00:00Z', 'MDT is UTC-6');
        assert.strictEqual(zonedToUtc('2027-03-06', '00:30', 'Asia/Tokyo'), '2027-03-05T15:30:00Z');
    });

    test('a blank or impossible pickup is no time', (assert) => {
        assert.strictEqual(zonedToUtc('', '11:00', 'UTC'), null);
        assert.strictEqual(zonedToUtc('2027-02-30', '11:00', 'UTC'), null);
        assert.strictEqual(zonedToUtc('2027-03-06', '11:00', 'Mars/Base'), null);
    });

    test("a distance in the profile's unit becomes whole meters", (assert) => {
        assert.deepEqual(distanceToMeters('12.5', 'km'), { value: 12500 });
        assert.deepEqual(distanceToMeters('0.001', 'km'), { value: 1 });
        assert.deepEqual(distanceToMeters('1', 'mi'), { value: 1609 });
        assert.deepEqual(distanceToMeters('', 'km'), { value: null });
        assert.strictEqual(distanceToMeters('-1', 'km').error, 'enhanced-pricing.console.input.distance-invalid');
        assert.strictEqual(distanceToMeters('1.0005', 'km').error, 'enhanced-pricing.console.input.distance-invalid');
    });
});
