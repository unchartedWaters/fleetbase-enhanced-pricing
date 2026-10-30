import { module, test } from 'qunit';
import { localPickup, distanceToMeters } from '@unchartedwaters/enhanced-pricing-engine/utils/preview';

// The preview takes a pickup in the order's own timezone and a distance in the profile's unit,
// and sends what the server takes: the pickup as typed with its timezone, and whole meters.
module('Unit | Utility | preview', () => {
    // The time goes as typed, on the order's clock, and the server reads it in the order's
    // timezone: the host console's Intl polyfill formats every timezone as UTC, so the browser
    // can't convert it.
    test('a pickup is sent as typed, with no offset', (assert) => {
        assert.strictEqual(localPickup('2027-03-06', '11:00'), '2027-03-06T11:00');
        assert.strictEqual(localPickup('2026-10-29', '22:30'), '2026-10-29T22:30');
    });

    test('a blank or impossible pickup is no time', (assert) => {
        assert.strictEqual(localPickup('', '11:00'), null);
        assert.strictEqual(localPickup('2027-03-06', ''), null);
        assert.strictEqual(localPickup('2027-02-30', '11:00'), null);
        assert.strictEqual(localPickup('2027-03-06', '24:00'), null);
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
