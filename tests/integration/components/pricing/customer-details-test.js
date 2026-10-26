import { module, test } from 'qunit';
import { render } from '@ember/test-helpers';
import { hbs } from 'ember-cli-htmlbars';
import { setupEngineRenderingTest, fixture } from 'dummy/tests/helpers/engine';

module('Integration | Component | pricing/customer-details', (hooks) => {
    setupEngineRenderingTest(hooks);

    test("each override is shown in its unit next to the profile's value", async function (assert) {
        this.customer = fixture('customer');

        await render(hbs`<Pricing::CustomerDetails @customer={{this.customer}} />`);

        assert.dom('[data-test-override="subtotal_fees.percent.percentage"] [data-test-value]').hasText('9%');
        assert.dom('[data-test-override="subtotal_fees.percent.percentage"] [data-test-default]').hasText('11%');
        assert.dom('[data-test-override="fees.heavy.minimum"] [data-test-value]').hasText('$5.00');
        assert.dom('[data-test-override="fees.heavy.minimum"] [data-test-default]').hasText('Not set');
    });

    test('a dated change in effect is shown with its date', async function (assert) {
        this.customer = fixture('customer');
        this.customer.overrides[1].change = { value: 1300, effective_from: '2026-12-01' };

        await render(hbs`<Pricing::CustomerDetails @customer={{this.customer}} />`);

        assert.dom('[data-test-override="subtotal_fees.percent.percentage"] [data-test-change]').hasText('13% from 2026-12-01');
    });

    test('cards show their rows, with money in the profile currency', async function (assert) {
        this.customer = fixture('customer');

        await render(hbs`<Pricing::CustomerDetails @customer={{this.customer}} />`);

        assert.dom('[data-test-card]').exists({ count: 1 });
        assert.dom('[data-test-card] [data-test-card-label]').hasText('Main');
        assert.dom('[data-test-row]').exists({ count: 2 });
        assert.dom('[data-test-row="R1"]').includesText('R1');
        assert.dom('[data-test-row="R1"] [data-test-column="first"]').hasText('$27.00', 'a money column in dollars');
        assert.dom('[data-test-row="R1"] [data-test-column="code"]').hasText('R1');
    });

    test('a stale customer and a stale row show their problems', async function (assert) {
        this.customer = fixture('customer');
        this.customer.problems = [{ path: 'fees.heavy.minimum', key: 'enhanced-pricing.profile.unknown-override', params: { path: 'fees.heavy.minimum' } }];
        this.customer.cards[0].rows[1].problems = [{ path: 'values.first', key: 'enhanced-pricing.store.row-missing-value', params: { column: 'first' } }];

        await render(hbs`<Pricing::CustomerDetails @customer={{this.customer}} />`);

        assert.dom('[data-test-customer-problems]').includesText('fees.heavy.minimum');
        assert.dom('[data-test-row="R2"] [data-test-problems]').includesText('The row needs a value for first.');
    });
});
