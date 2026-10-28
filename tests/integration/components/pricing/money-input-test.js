import { module, test } from 'qunit';
import { render, fillIn } from '@ember/test-helpers';
import { hbs } from 'ember-cli-htmlbars';
import { setupEngineRenderingTest } from 'dummy/tests/helpers/engine';

// A money field is typed in the profile currency's own decimals: whole yen, cents, or fils.
module('Integration | Component | pricing/money-input', (hooks) => {
    setupEngineRenderingTest(hooks);

    hooks.beforeEach(function () {
        this.value = null;
        this.failed = false;
        this.change = (value) => this.set('value', value);
        this.error = (failed) => this.set('failed', failed);
    });

    test('dollars are saved as cents', async function (assert) {
        await render(hbs`<Pricing::MoneyInput @value={{this.value}} @currency="USD" @onChange={{this.change}} @onError={{this.error}} />`);

        await fillIn('input', '45.50');

        assert.strictEqual(this.value, 4550);
        assert.dom('[data-test-stored]').hasText('Saved as 4550 cents');
    });

    test('yen are saved whole, and a decimal is refused', async function (assert) {
        this.value = 4500;
        await render(hbs`<Pricing::MoneyInput @value={{this.value}} @currency="JPY" @onChange={{this.change}} @onError={{this.error}} />`);
        assert.dom('input').hasValue('4500');

        await fillIn('input', '1200');
        assert.strictEqual(this.value, 1200);
        assert.dom('[data-test-stored]').hasText('Saved as 1200 in JPY, which has no minor unit');

        await fillIn('input', '12.50');
        assert.strictEqual(this.value, 1200, 'the refused amount is not sent');
        assert.true(this.failed);
        assert.dom('[data-test-input-error]').hasText('Type a whole amount such as 45, with no decimals, sign or symbol.');
    });

    test('dinars are saved as thousandths', async function (assert) {
        this.value = 1234;
        await render(hbs`<Pricing::MoneyInput @value={{this.value}} @currency="KWD" @onChange={{this.change}} @onError={{this.error}} />`);
        assert.dom('input').hasValue('1.234');

        await fillIn('input', '2.5');
        assert.strictEqual(this.value, 2500);
        assert.dom('[data-test-stored]').hasText('Saved as 2500 thousandths of a KWD');

        await fillIn('input', '2.5001');
        assert.dom('[data-test-input-error]').hasText('Type an amount such as 45 or 45.5, with up to 3 decimals and no sign or symbol.');
    });

    test('percents are unchanged by the currency', async function (assert) {
        await render(hbs`<Pricing::MoneyInput @value={{this.value}} @kind="percent" @currency="JPY" @onChange={{this.change}} @onError={{this.error}} />`);

        await fillIn('input', '12.5');

        assert.strictEqual(this.value, 1250);
        assert.dom('[data-test-stored]').hasText('Saved as 1250 basis points');
    });
});
