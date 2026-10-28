import { module, test } from 'qunit';
import { render, click } from '@ember/test-helpers';
import { hbs } from 'ember-cli-htmlbars';
import { setupEngineRenderingTest, fixture } from 'dummy/tests/helpers/engine';

module('Integration | Component | pricing/customers-list', (hooks) => {
    setupEngineRenderingTest(hooks);

    test('each customer shows its overrides, cards and whether it is stale', async function (assert) {
        this.page = fixture('customers');
        this.page.customers[1].problems = [{ path: 'fees.heavy.minimum', key: 'enhanced-pricing.profile.unknown-override', params: { path: 'fees.heavy.minimum' } }];
        this.opened = [];
        this.open = (uuid) => this.opened.push(uuid);

        await render(hbs`<Pricing::CustomersList @page={{this.page}} @onOpen={{this.open}} />`);

        const [first, second] = this.page.customers;
        assert.dom(`[data-test-customer="${first.uuid}"]`).includesText('Customer a 1');
        assert.dom(`[data-test-customer="${first.uuid}"] [data-test-overrides]`).hasText('2');
        assert.dom(`[data-test-customer="${first.uuid}"] [data-test-cards]`).hasText('1');
        assert.dom(`[data-test-customer="${first.uuid}"] [data-test-stale]`).doesNotExist();
        assert.dom(`[data-test-customer="${second.uuid}"] [data-test-stale]`).hasText('Needs attention');

        await click(`[data-test-customer="${first.uuid}"] [data-test-open]`);

        assert.deepEqual(this.opened, [first.uuid]);
    });

    test('a long list is paged', async function (assert) {
        this.page = { ...fixture('customers'), total: 120, page: 2, per_page: 50 };
        this.pages = [];
        this.go = (page) => this.pages.push(page);

        await render(hbs`<Pricing::CustomersList @page={{this.page}} @onPage={{this.go}} />`);

        assert.dom('[data-test-paging]').includesText('51–100 of 120');
        await click('[data-test-next]');
        await click('[data-test-previous]');

        assert.deepEqual(this.pages, [3, 1]);
    });
});
