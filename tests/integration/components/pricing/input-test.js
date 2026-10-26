import { module, test } from 'qunit';
import { render } from '@ember/test-helpers';
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
});
