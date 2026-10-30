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

    test('the page says prices are before GST, which a QuickBooks sync adds to Ledger invoices', async function (assert) {
        for (const profiles of [fixture('profiles').profiles, []]) {
            this.profiles = profiles;

            await render(hbs`<Pricing::ProfilesList @profiles={{this.profiles}} />`);

            assert.dom('[data-test-gst-notice]').includesText('Prices here are before GST');
            assert.dom('[data-test-gst-notice]').includesText('invoices sync both ways, QuickBooks is Primary and works out the tax');
            assert.dom('[data-test-gst-notice]').includesText('the next QuickBooks sync copies the GST QuickBooks charges');
            assert.dom('[data-test-gst-notice]').includesText('Check that a Ledger invoice shows GST before sending a customer its public link');
            assert.dom('[data-test-gst-notice]').includesText('payment gateway');
            assert.dom('[data-test-gst-notice]').doesNotIncludeText('show totals without GST');
        }
    });
});
