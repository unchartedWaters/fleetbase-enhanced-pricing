import { module, test } from 'qunit';
import { render, click, fillIn } from '@ember/test-helpers';
import { hbs } from 'ember-cli-htmlbars';
import { setupEngineRenderingTest, fixture } from 'dummy/tests/helpers/engine';

// Subtotal fees: each fee's value today, its upcoming changes, the customers a change won't
// reach, and scheduling or cancelling a change to its global value.
module('Integration | Component | pricing/subtotal-fees', (hooks) => {
    setupEngineRenderingTest(hooks);

    hooks.beforeEach(function () {
        this.schedule = fixture('schedule');
        this.sent = [];
        this.cancelled = [];
        this.answer = () => Promise.resolve({});
        this.add = (change) => {
            this.sent.push(change);

            return this.answer(change);
        };
        this.cancel = (uuid) => {
            this.cancelled.push(uuid);

            return Promise.resolve({});
        };
    });

    test('each fee shows its value today in its unit, and its upcoming changes', async function (assert) {
        await render(hbs`<Pricing::SubtotalFees @schedule={{this.schedule}} @currency="USD" />`);

        assert.dom('[data-test-fee="percent"] [data-test-current]').hasText('11%');
        assert.dom('[data-test-fee="flat_a"] [data-test-current]').hasText('$4.00');
        assert.dom('[data-test-fee="percent"] [data-test-upcoming]').includesText('13% from 2026-12-01');
        assert.dom('[data-test-fee="percent"] [data-test-upcoming]').includesText('User a');
    });

    test("customers with their own value are named, since a change won't reach them", async function (assert) {
        await render(hbs`<Pricing::SubtotalFees @schedule={{this.schedule}} @currency="USD" />`);

        assert.dom('[data-test-fee="percent"] [data-test-overridden]').includesText('Customer a 1 (9%)');
        assert.dom('[data-test-fee="flat_a"] [data-test-overridden]').doesNotExist();
    });

    test('a percentage change is typed in percent and sent in basis points', async function (assert) {
        await render(hbs`<Pricing::SubtotalFees @schedule={{this.schedule}} @currency="USD" @onSchedule={{this.add}} />`);

        await fillIn('[data-test-fee="percent"] [data-test-new-value] input', '31');
        assert.dom('[data-test-fee="percent"] [data-test-new-value]').includesText('3100 basis points');
        await fillIn('[data-test-fee="percent"] [data-test-new-date]', '2027-01-01');
        await click('[data-test-fee="percent"] [data-test-schedule]');

        assert.deepEqual(this.sent, [{ path: 'subtotal_fees.percent.percentage', value: 3100, effective_from: '2027-01-01', accept_large_change: false }]);
    });

    test('an amount change is typed in dollars and sent in cents', async function (assert) {
        await render(hbs`<Pricing::SubtotalFees @schedule={{this.schedule}} @currency="USD" @onSchedule={{this.add}} />`);

        await fillIn('[data-test-fee="flat_a"] [data-test-new-value] input', '4.5');
        await fillIn('[data-test-fee="flat_a"] [data-test-new-date]', '2027-01-01');
        await click('[data-test-fee="flat_a"] [data-test-schedule]');

        assert.strictEqual(this.sent[0].value, 450);
    });

    test("a value that is not a plain amount can't be scheduled", async function (assert) {
        await render(hbs`<Pricing::SubtotalFees @schedule={{this.schedule}} @currency="USD" @onSchedule={{this.add}} />`);

        await fillIn('[data-test-fee="flat_a"] [data-test-new-value] input', '4.555');
        await fillIn('[data-test-fee="flat_a"] [data-test-new-date]', '2027-01-01');

        assert.dom('[data-test-fee="flat_a"] [data-test-new-value]').includesText('Type an amount such as 45 or 45.50');
        assert.dom('[data-test-fee="flat_a"] [data-test-schedule]').isDisabled();
    });

    test('a large change asks for a confirm and is sent again accepted', async function (assert) {
        this.answer = (change) =>
            change.accept_large_change
                ? Promise.resolve({})
                : Promise.reject({
                      refused: 'large-change',
                      problems: [{ path: 'value', key: 'enhanced-pricing.store.large-change', params: { path: 'subtotal_fees.percent.percentage', from: '11', to: '31' } }],
                  });
        await render(hbs`<Pricing::SubtotalFees @schedule={{this.schedule}} @currency="USD" @onSchedule={{this.add}} />`);

        await fillIn('[data-test-fee="percent"] [data-test-new-value] input', '31');
        await fillIn('[data-test-fee="percent"] [data-test-new-date]', '2027-01-01');
        await click('[data-test-fee="percent"] [data-test-schedule]');

        assert.dom('[data-test-fee="percent"] [data-test-large-change]').exists();
        await click('[data-test-fee="percent"] [data-test-confirm-large-change]');

        assert.deepEqual(
            this.sent.map((change) => change.accept_large_change),
            [false, true]
        );
        assert.dom('[data-test-fee="percent"] [data-test-large-change]').doesNotExist();
    });

    test("a refused change shows the server's reason", async function (assert) {
        this.answer = () =>
            Promise.reject({
                refused: 'invalid',
                problems: [{ path: 'effective_from', key: 'enhanced-pricing.store.change-too-soon', params: { today: '2026-11-02' } }],
            });
        await render(hbs`<Pricing::SubtotalFees @schedule={{this.schedule}} @currency="USD" @onSchedule={{this.add}} />`);

        await fillIn('[data-test-fee="percent"] [data-test-new-value] input', '12');
        await fillIn('[data-test-fee="percent"] [data-test-new-date]', '2026-11-02');
        await click('[data-test-fee="percent"] [data-test-schedule]');

        assert.dom('[data-test-fee="percent"] [data-test-problems]').hasText('A change must be dated after today, 2026-11-02.');
    });

    test('an upcoming change can be cancelled after a confirm', async function (assert) {
        await render(hbs`<Pricing::SubtotalFees @schedule={{this.schedule}} @currency="USD" @onCancel={{this.cancel}} />`);
        const uuid = this.schedule.subtotal_fees[0].upcoming[0].uuid;

        await click(`[data-test-upcoming-change="${uuid}"] [data-test-cancel]`);
        assert.deepEqual(this.cancelled, [], 'asks first');
        await click(`[data-test-upcoming-change="${uuid}"] [data-test-confirm-cancel]`);

        assert.deepEqual(this.cancelled, [uuid]);
    });

    test('every stored change is listed with its state', async function (assert) {
        this.schedule.changes.push({ ...this.schedule.changes[0], uuid: 'x', path: 'fees.heavy.minimum', value: 100, state: 'stale' });
        await render(hbs`<Pricing::SubtotalFees @schedule={{this.schedule}} @currency="USD" />`);

        assert.dom('[data-test-change]').exists({ count: 2 });
        assert.dom('[data-test-change="x"]').includesText('fees.heavy.minimum');
        assert.dom('[data-test-change="x"]').includesText('$1.00');
        assert.dom('[data-test-change="x"]').includesText('Stale');
    });
});
