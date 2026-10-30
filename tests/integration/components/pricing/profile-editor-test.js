import { module, test } from 'qunit';
import { render, click, fillIn, select, findAll, triggerEvent } from '@ember/test-helpers';
import { hbs } from 'ember-cli-htmlbars';
import { setupEngineRenderingTest, fixture } from 'dummy/tests/helpers/engine';

// The profile editor: every element of a profile in forms drawn from the server's field spec,
// money in dollars and percents in percent, each save a new version built on the one opened.
module('Integration | Component | pricing/profile-editor', (hooks) => {
    setupEngineRenderingTest(hooks);

    hooks.beforeEach(function () {
        const profile = fixture('profile');
        this.form = fixture('form');
        this.profile = profile;
        this.definition = profile.version.definition;
        this.saved = [];
        this.answer = () => Promise.resolve(fixture('profile'));
        this.save = (payload) => {
            this.saved.push(JSON.parse(JSON.stringify(payload)));

            return this.answer(payload);
        };
        this.newer = [];
        this.openNewer = () => this.newer.push(true);
    });

    const editor = hbs`<Pricing::ProfileEditor @form={{this.form}} @profile={{this.profile}} @definition={{this.definition}} @builtOn={{2}} @onSave={{this.save}} @onOpenNewer={{this.openNewer}} />`;

    test('every section is listed and each shows its elements', async function (assert) {
        await render(editor);

        assert.deepEqual(
            findAll('[data-test-section]').map((element) => element.dataset.testSection),
            ['general', 'products', 'attributes', 'entity_types', 'rate_tables', 'service_rates', 'fees', 'subtotal_fees', 'manual_charges', 'holiday_lists']
        );
        await click('[data-test-section="fees"]');

        assert.dom('[data-test-item]').exists({ count: 3 });
        assert.dom('[data-test-item="fees.2"]').includesText('Peak hours fee');
    });

    test('money is shown and typed in dollars and saved in cents', async function (assert) {
        await render(editor);
        await click('[data-test-section="fees"]');

        assert.dom('[data-test-field="fees.2.amount"] input').hasValue('22.00');
        await fillIn('[data-test-field="fees.2.amount"] input', '31');
        assert.dom('[data-test-field="fees.2.amount"]').includesText('3100 cents');
        await click('[data-test-save]');

        assert.strictEqual(this.saved[0].definition.fees[2].amount, 3100);
        assert.strictEqual(this.saved[0].built_on, 2);
        assert.deepEqual(this.saved[0].accept_stale, []);
    });

    test('a percent is typed in percent and saved in basis points', async function (assert) {
        await render(editor);
        await click('[data-test-section="subtotal_fees"]');

        assert.dom('[data-test-field="subtotal_fees.0.percentage"] input').hasValue('11');
        await fillIn('[data-test-field="subtotal_fees.0.percentage"] input', '0.31');
        assert.dom('[data-test-field="subtotal_fees.0.percentage"]').includesText('31 basis points');
        await click('[data-test-save]');

        assert.strictEqual(this.saved[0].definition.subtotal_fees[0].percentage, 31);
    });

    test('a value that is not a plain amount stops the save', async function (assert) {
        await render(editor);
        await click('[data-test-section="fees"]');
        await fillIn('[data-test-field="fees.2.amount"] input', '22.555');

        assert.dom('[data-test-field="fees.2.amount"]').includesText('Type an amount such as 45 or 45.50');
        assert.dom('[data-test-save]').isDisabled();
    });

    // A profile may leave out an optional flag; its default then applies, so the box shows it.
    test('a flag the profile leaves out shows its default, and is saved only when changed', async function (assert) {
        this.definition = JSON.parse(JSON.stringify(this.definition));
        delete this.definition.fees[2].enabled;
        await render(editor);
        await click('[data-test-section="fees"]');

        assert.dom('[data-test-field="fees.2.enabled"] input[type="checkbox"]').isChecked('enabled by default');
        await click('[data-test-save]');
        assert.false('enabled' in this.saved[0].definition.fees[2], 'nothing is written for an untouched flag');

        await click('[data-test-field="fees.2.enabled"] input[type="checkbox"]');
        assert.dom('[data-test-field="fees.2.enabled"] input[type="checkbox"]').isNotChecked();
        await click('[data-test-save]');
        assert.false(this.saved[1].definition.fees[2].enabled);
    });

    test('elements are added, moved and removed, and the order is saved', async function (assert) {
        await render(editor);
        await click('[data-test-section="fees"]');

        await click('[data-test-item="fees.2"] [data-test-move-up]');
        assert.dom('[data-test-item="fees.1"]').includesText('Peak hours fee');
        await click('[data-test-item="fees.0"] [data-test-remove]');
        await select('[data-test-add-variant]', 'additional');
        await click('[data-test-add]');
        await click('[data-test-save]');

        const fees = this.saved[0].definition.fees;
        assert.deepEqual(
            fees.map((fee) => fee.key),
            ['peak', 'heavy', '']
        );
        assert.strictEqual(fees[2].kind, 'additional');
    });

    test('a variant switch keeps the shared fields and shows the new ones', async function (assert) {
        await render(editor);
        await click('[data-test-section="fees"]');

        await select('[data-test-item="fees.2"] [data-test-variant]', 'parcel');

        assert.dom('[data-test-field="fees.2.key"] input').hasValue('peak');
        assert.dom('[data-test-field="fees.2.window"]').doesNotExist();
        assert.dom('[data-test-field="fees.2.basis"]').exists();
        assert.dom('[data-test-field="fees.2.measure"]').doesNotExist('only for weight');
        await select('[data-test-field="fees.2.basis"] select', 'weight');
        assert.dom('[data-test-field="fees.2.measure"]').exists();
    });

    test("a field the format doesn't know is kept and can be removed", async function (assert) {
        this.definition.products[0].priced = true;
        await render(editor);
        await click('[data-test-section="products"]');

        assert.dom('[data-test-unknown="products.0.priced"]').includesText('priced');
        await click('[data-test-save]');
        assert.true(this.saved[0].definition.products[0].priced, 'kept');

        await click('[data-test-unknown="products.0.priced"] [data-test-remove-unknown]');
        await click('[data-test-save]');
        assert.false('priced' in this.saved[1].definition.products[0], 'removed');
    });

    test('references choose from what the profile holds', async function (assert) {
        await render(editor);
        await click('[data-test-section="service_rates"]');

        const options = findAll('[data-test-field="service_rates.0.product"] option').map((option) => option.value);
        assert.true(options.includes('TIERED'));
        assert.true(options.includes('HOURLY'));
        assert.dom('[data-test-field="service_rates.0.tiers.0.price"] select[data-test-choice-value]').hasValue('first');
    });

    test("the currency and distance unit can't change once the profile exists", async function (assert) {
        await render(editor);

        assert.dom('[data-test-field="currency"] input').isDisabled();
        assert.dom('[data-test-field="distance_unit"] select').isDisabled();
        assert.dom('[data-test-field="scheduling_timezone"] input').isNotDisabled();
    });

    test('a refused save lists every problem and marks each field', async function (assert) {
        this.answer = () => Promise.reject(fixture('refused').invalid);
        await render(editor);
        await click('[data-test-save]');

        assert.dom('[data-test-problem]').exists({ count: 2 });
        assert.dom('[data-test-problems]').includesText('products.1.code');
        assert.dom('[data-test-section="fees"] [data-test-problem-count]').hasText('1');

        await click('[data-test-problem="fees.0.minimum"] button');

        assert.dom('[data-test-field="fees.0.minimum"] [data-test-field-problems]').hasText('The value must be from 0 to 100000000.');
    });

    test('moving an element clears the marks of its section, whose paths no longer fit', async function (assert) {
        this.answer = () => Promise.reject(fixture('refused').invalid);
        await render(editor);
        await click('[data-test-save]');
        await click('[data-test-section="fees"]');

        await click('[data-test-item="fees.1"] [data-test-move-up]');

        assert.dom('[data-test-field-problems]').doesNotExist();
        assert.dom('[data-test-problem]').exists({ count: 1 }, 'the products problem stays');
    });

    test('stale entries are named, and saving anyway sends the ones shown', async function (assert) {
        const stale = fixture('refused').stale;
        this.answer = (payload) => (payload.accept_stale.length ? Promise.resolve(fixture('profile')) : Promise.reject(stale));
        await render(editor);
        await click('[data-test-save]');

        assert.dom('[data-test-stale]').includesText('Customer a 1');
        assert.dom('[data-test-stale]').includesText('There is no fee named heavy.');
        await click('[data-test-save-stale]');

        assert.deepEqual(this.saved[1].accept_stale, [stale.stale[0].uuid]);
        assert.dom('[data-test-stale]').doesNotExist();
    });

    test('a newer version is named, and can be opened or replaced', async function (assert) {
        this.answer = (payload) => (payload.built_on === null ? Promise.resolve(fixture('profile')) : Promise.reject(fixture('refused').outdated));
        await render(editor);
        await click('[data-test-save]');

        assert.dom('[data-test-outdated]').includesText('Version 2');
        assert.dom('[data-test-outdated]').includesText('User a');
        await click('[data-test-open-newer]');
        await click('[data-test-confirm-open-newer]');
        assert.deepEqual(this.newer, [true]);

        await click('[data-test-replace]');
        await click('[data-test-confirm-replace]');
        assert.strictEqual(this.saved[1].built_on, null);
    });

    test('a new profile takes its key and name, and its currency and unit', async function (assert) {
        this.blank = { currency: 'CAD', distance_unit: 'km', products: [], entity_types: [], service_rates: [] };
        await render(hbs`<Pricing::ProfileEditor @form={{this.form}} @definition={{this.blank}} @isNew={{true}} @onSave={{this.save}} />`);

        await fillIn('[data-test-new-key]', 'courier');
        await fillIn('[data-test-new-name]', 'Courier');
        assert.dom('[data-test-field="currency"] input').isNotDisabled();
        await click('[data-test-save]');

        assert.strictEqual(this.saved[0].key, 'courier');
        assert.strictEqual(this.saved[0].name, 'Courier');
        assert.strictEqual(this.saved[0].definition.currency, 'CAD');
        assert.false('built_on' in this.saved[0]);
    });

    test('a version can be read without editing it', async function (assert) {
        await render(hbs`<Pricing::ProfileEditor @form={{this.form}} @profile={{this.profile}} @definition={{this.definition}} @readOnly={{true}} />`);
        await click('[data-test-section="fees"]');

        assert.dom('[data-test-save]').doesNotExist();
        assert.dom('[data-test-add]').doesNotExist();
        assert.dom('[data-test-field="fees.2.amount"] input').isDisabled();
    });

    test('holiday lists are named lists of dates', async function (assert) {
        this.definition.holiday_lists = { canada: ['2026-12-25'] };
        await render(editor);
        await click('[data-test-section="holiday_lists"]');

        assert.dom('[data-test-holiday-list="canada"] textarea').hasValue('2026-12-25');
        await fillIn('[data-test-holiday-list="canada"] textarea', '2026-12-25\n2026-12-26');
        await click('[data-test-save]');

        assert.deepEqual(this.saved[0].definition.holiday_lists, { canada: ['2026-12-25', '2026-12-26'] });
    });

    test('changes are reported, so leaving can ask first', async function (assert) {
        this.dirty = [];
        this.onDirty = (dirty) => this.dirty.push(dirty);
        await render(
            hbs`<Pricing::ProfileEditor @form={{this.form}} @profile={{this.profile}} @definition={{this.definition}} @builtOn={{2}} @onSave={{this.save}} @onDirty={{this.onDirty}} />`
        );

        await fillIn('[data-test-field="scheduling_timezone"] input', 'America/Edmonton');
        await click('[data-test-save]');

        assert.deepEqual(this.dirty, [true, false]);
    });

    test("a stored fee's empty fields of another mode or basis aren't called unknown", async function (assert) {
        await render(editor);
        await click('[data-test-section="subtotal_fees"]');
        assert.dom('[data-test-unknown]').doesNotExist();

        await click('[data-test-section="fees"]');
        assert.dom('[data-test-unknown]').doesNotExist();
    });

    test("text that wasn't saved doesn't follow a moved element onto another", async function (assert) {
        await render(editor);
        await click('[data-test-section="fees"]');
        await fillIn('[data-test-field="fees.0.minimum"] input', '1.234');
        assert.dom('[data-test-save]').isDisabled();

        await click('[data-test-item="fees.0"] [data-test-move-down]');

        assert.dom('[data-test-field="fees.0.minimum"] input').hasValue('');
        assert.dom('[data-test-field="fees.1.minimum"] input').hasValue('');
        assert.dom('[data-test-save]').isNotDisabled();
    });

    test('saving anyway after replacing a newer version still replaces it', async function (assert) {
        this.answer = (payload) => {
            if (payload.built_on !== null) {
                return Promise.reject(fixture('refused').outdated);
            }

            return payload.accept_stale.length ? Promise.resolve(fixture('profile')) : Promise.reject(fixture('refused').stale);
        };
        await render(editor);
        await click('[data-test-save]');
        await click('[data-test-replace]');
        await click('[data-test-confirm-replace]');
        await click('[data-test-save-stale]');

        assert.strictEqual(this.saved[2].built_on, null);
        assert.dom('[data-test-stale]').doesNotExist();
        assert.dom('[data-test-outdated]').doesNotExist();
    });

    test("the newer version's time is shown as a time", async function (assert) {
        this.answer = () => Promise.reject(fixture('refused').outdated);
        await render(editor);
        await click('[data-test-save]');

        assert.dom('[data-test-outdated]').doesNotIncludeText(fixture('refused').outdated.newer.saved_at);
    });

    test("a holiday list can't take a blank name or another list's name", async function (assert) {
        this.definition.holiday_lists = { canada: ['2026-12-25'], alberta: ['2026-08-03'] };
        await render(editor);
        await click('[data-test-section="holiday_lists"]');

        await fillIn('[data-test-holiday-list="canada"] [data-test-list-name]', 'alberta');
        assert.dom('[data-test-list-name-error]').exists();
        assert.dom('[data-test-holiday-list="canada"] [data-test-list-name]').hasValue('canada');
        await fillIn('[data-test-holiday-list="canada"] [data-test-list-name]', ' ');
        await triggerEvent('[data-test-holiday-list="canada"] [data-test-list-name]', 'change');
        await click('[data-test-save]');

        assert.deepEqual(this.saved[0].definition.holiday_lists, { canada: ['2026-12-25'], alberta: ['2026-08-03'] });
    });

    test("a new profile's key and name count as changes", async function (assert) {
        this.dirty = [];
        this.onDirty = (dirty) => this.dirty.push(dirty);
        this.blank = { currency: 'CAD', distance_unit: 'km' };
        await render(hbs`<Pricing::ProfileEditor @form={{this.form}} @definition={{this.blank}} @isNew={{true}} @onSave={{this.save}} @onDirty={{this.onDirty}} />`);

        await fillIn('[data-test-new-key]', 'courier');

        assert.deepEqual(this.dirty, [true]);
    });
});
