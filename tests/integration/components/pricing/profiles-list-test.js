import { module, test } from 'qunit';
import { render, click } from '@ember/test-helpers';
import { hbs } from 'ember-cli-htmlbars';
import { setupEngineRenderingTest, fixture } from 'dummy/tests/helpers/engine';

module('Integration | Component | pricing/profiles-list', (hooks) => {
    setupEngineRenderingTest(hooks);

    test('each profile shows its version, who saved it and its customers', async function (assert) {
        this.profiles = fixture('profiles').profiles;
        this.opened = [];
        this.open = (key) => this.opened.push(key);

        await render(hbs`<Pricing::ProfilesList @profiles={{this.profiles}} @onOpen={{this.open}} />`);

        assert.dom('[data-test-profile="main"]').includesText('Main');
        assert.dom('[data-test-profile="main"]').includesText('USD');
        assert.dom('[data-test-profile="main"] [data-test-version]').hasText('2');
        assert.dom('[data-test-profile="main"] [data-test-saved-by]').hasText('User a');
        assert.dom('[data-test-profile="main"] [data-test-customers]').hasText('2');

        await click('[data-test-profile="main"] [data-test-open]');

        assert.deepEqual(this.opened, ['main']);
    });

    test('with no profiles it says how to start', async function (assert) {
        this.profiles = [];

        await render(hbs`<Pricing::ProfilesList @profiles={{this.profiles}} />`);

        assert.dom('[data-test-empty]').hasText('No pricing profiles yet. Create one to start pricing orders.');
    });

    test('the page warns that prices are before GST, which only QuickBooks adds', async function (assert) {
        for (const profiles of [fixture('profiles').profiles, []]) {
            this.profiles = profiles;

            await render(hbs`<Pricing::ProfilesList @profiles={{this.profiles}} />`);

            assert.dom('[data-test-gst-notice]').includesText('QuickBooks');
            assert.dom('[data-test-gst-notice]').includesText("Don't send customers Ledger's public invoice link");
            assert.dom('[data-test-gst-notice]').includesText('payment gateway');
        }
    });
});
