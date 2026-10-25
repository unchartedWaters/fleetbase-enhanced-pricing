import { module, test } from 'qunit';
import { setupTest } from 'dummy/tests/helpers';
import extension from '@unchartedwaters/enhanced-pricing-engine/extension';

module('Unit | extension', (hooks) => {
    setupTest(hooks);

    test('setupExtension registers no menus until the console screens exist', (assert) => {
        const calls = [];
        const record = (name) => () => calls.push(name);
        const menu = {
            registerHeaderMenuItem: record('header'),
            registerAdminMenuPanel: record('admin-panel'),
            registerSettingsMenuItem: record('settings-item'),
        };
        const universe = { getService: () => menu };

        extension.setupExtension({ lookup: () => null }, universe);

        assert.deepEqual(calls, []);
    });
});
