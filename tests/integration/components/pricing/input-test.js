import { module, test } from 'qunit';
import { render, fillIn } from '@ember/test-helpers';
import { hbs } from 'ember-cli-htmlbars';
import { setupEngineRenderingTest } from 'dummy/tests/helpers/engine';

module('Integration | Component | pricing/input', (hooks) => {
    setupEngineRenderingTest(hooks);

    for (const type of ['text', 'integer', 'date', 'time', 'boolean', 'enum', 'refs', 'weekdays', 'dates', 'money']) {
        test(`a ${type} value renders`, async function (assert) {
            this.type = type;
            this.noop = () => {};
            await render(hbs`<Pricing::Input @type={{this.type}} @value={{null}} @options={{(array)}} @onChange={{this.noop}} />`);
            assert.dom(this.element).exists();
        });
    }

    test('a whole number shows the value it is given, not text typed before', async function (assert) {
        this.value = 2;
        this.change = (value) => this.set('value', value);
        await render(hbs`<Pricing::Input @type="integer" @value={{this.value}} @onChange={{this.change}} />`);

        await fillIn('input', '3');
        assert.strictEqual(this.value, 3);
        this.set('value', 5);

        assert.dom('input').hasValue('5');
    });
});
