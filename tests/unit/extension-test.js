import { module, test } from 'qunit';
import { setupTest } from 'dummy/tests/helpers';
import extension from '@unchartedwaters/enhanced-pricing-engine/extension';
import { MenuItem } from '@fleetbase/ember-core/contracts';

module('Unit | extension', (hooks) => {
    setupTest(hooks);

    test("setupExtension registers the header item under the extension's own id", (assert) => {
        const calls = [];
        const menu = {
            registerHeaderMenuItem: (item) => calls.push(['header', item]),
            registerAdminMenuPanel: () => calls.push(['admin-panel']),
            registerSettingsMenuItem: () => calls.push(['settings-item']),
        };
        const universe = { getService: (name) => (name === 'universe/menu-service' ? menu : null) };

        extension.setupExtension({ lookup: () => null }, universe);

        assert.strictEqual(calls.length, 1, 'only the header item');
        const [kind, item] = calls[0];
        assert.strictEqual(kind, 'header');
        assert.true(item instanceof MenuItem);
        // The header shows the item only to users who can "{id} see extension".
        assert.strictEqual(item.id, 'enhanced-pricing');
        assert.strictEqual(item.route, 'console.enhanced-pricing');
        assert.deepEqual(
            item.shortcuts.map((shortcut) => shortcut.route),
            ['console.enhanced-pricing.profiles', 'console.enhanced-pricing.preview']
        );
    });
});
