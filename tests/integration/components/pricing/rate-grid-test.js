import { module, test } from 'qunit';
import { render, click, fillIn, triggerEvent, triggerKeyEvent, waitUntil } from '@ember/test-helpers';
import Service from '@ember/service';
import { hbs } from 'ember-cli-htmlbars';
import { setupEngineRenderingTest, fixture } from 'dummy/tests/helpers/engine';

// The rate table grid: edit like a spreadsheet, save the table's rows, upload and download.
module('Integration | Component | pricing/rate-grid', (hooks) => {
    setupEngineRenderingTest(hooks);

    const table = {
        key: 'rows',
        row_key: ['code'],
        columns: [
            { key: 'code', name: 'Route', type: 'text', required: true },
            { key: 'first', name: null, type: 'money', required: true },
            { key: 'included', name: null, type: 'integer', required: false },
        ],
    };

    hooks.beforeEach(function () {
        this.calls = [];
        this.reply = (call) => ({ rows: call.data?.rows?.length ?? 0, token: 'next' });
        const test = this;
        this.owner.register(
            'service:pricing-api',
            class extends Service {
                segment(value) {
                    return value;
                }
                async put(path, data) {
                    return test.respond('put', path, data);
                }
                async post(path, data) {
                    return test.respond('post', path, data);
                }
                async get(path, query) {
                    return test.respond('get', path, query);
                }
            }
        );
        this.respond = async (method, path, data) => {
            const call = { method, path, data };
            this.calls.push(call);
            const answer = this.reply(call);
            if (answer instanceof Error || answer?.refused) {
                throw answer;
            }

            return answer;
        };
        this.card = fixture('customer').cards[0];
        this.card.tokens = { rows: 'tok' };
        this.table = table;
        this.changed = 0;
        this.onChanged = () => this.changed++;
    });

    // The file is read asynchronously, so wait for the request, or for the refusal of a file too big.
    async function choose(test, file) {
        const before = test.calls.length;
        const input = document.querySelector('[data-test-upload]');
        const files = new DataTransfer();
        files.items.add(file);
        input.files = files.files;
        await triggerEvent(input, 'change');
        await waitUntil(() => test.calls.length > before || document.querySelector('[data-test-problems]'), { timeout: 2000 });
    }

    async function open() {
        await render(hbs`<Pricing::RateGrid @customer="cust" @card={{this.card}} @table={{this.table}} @currency="USD" @onChanged={{this.onChanged}} />`);
        await click('[data-test-grid-toggle]');
    }

    test('the grid is closed until opened, and shows the saved rows with money as decimals', async function (assert) {
        await render(hbs`<Pricing::RateGrid @customer="cust" @card={{this.card}} @table={{this.table}} @currency="USD" />`);

        assert.dom('[data-test-grid-table]').doesNotExist();
        assert.dom('[data-test-grid-toggle]').includesText('2 rows');

        await click('[data-test-grid-toggle]');

        assert.dom('[data-test-grid-row]').exists({ count: 2 });
        assert.dom('[data-test-cell="1.code"]').hasValue('R1');
        assert.dom('[data-test-cell="1.first"]').hasValue('27.00');
        assert.dom('[data-test-cell="2.first"]').hasValue('27.05');
        assert.dom('[data-test-save-rows]').isDisabled('nothing to save yet');
    });

    test('saving sends every row with money in minor units, the table and the token it was loaded with', async function (assert) {
        await open();

        await fillIn('[data-test-cell="1.first"]', '30.5');
        await click('[data-test-add-row]');
        await fillIn('[data-test-cell="3.code"]', ' R3 ');
        await fillIn('[data-test-cell="3.included"]', '4');
        await click('[data-test-save-rows]');

        assert.deepEqual(this.calls[0], {
            method: 'put',
            path: 'admin/rate-cards/cust/rows',
            data: {
                card: 'Main',
                table: 'rows',
                replace: true,
                token: 'tok',
                rows: [
                    { line: 1, values: { code: 'R1', first: 3050 } },
                    { line: 2, values: { code: 'R2', first: 2705 } },
                    { line: 3, values: { code: 'R3', included: 4 } },
                ],
            },
        });
        assert.dom('[data-test-saved]').includesText('Saved 3 rows');
        assert.strictEqual(this.changed, 1, 'the screen is reloaded');
        assert.dom('[data-test-cell="1.first"]').hasValue('30.5', 'the grid keeps what was typed');
    });

    test('the next save sends the token the last save returned', async function (assert) {
        await open();

        await fillIn('[data-test-cell="1.first"]', '1');
        await click('[data-test-save-rows]');
        await fillIn('[data-test-cell="1.first"]', '2');
        await click('[data-test-save-rows]');

        assert.deepEqual([this.calls[0].data.token, this.calls[1].data.token], ['tok', 'next']);
    });

    test('a value that is not an amount is marked and nothing is sent', async function (assert) {
        await open();

        await fillIn('[data-test-cell="2.first"]', '$5');
        await click('[data-test-save-rows]');

        assert.strictEqual(this.calls.length, 0);
        assert.dom('[data-test-cell="2.first"]').hasAttribute('aria-invalid', 'true');
        assert.dom('[data-test-cell-error]').exists({ count: 1 });
        await fillIn('[data-test-cell="2.first"]', '5');
        assert.dom('[data-test-cell-error]').doesNotExist('typing clears the mark');
    });

    test("the server's refusal is shown at the cell it names, by the row number the person sees", async function (assert) {
        this.reply = () => ({
            refused: 'invalid',
            problems: [{ path: '2.values.first', key: 'enhanced-pricing.store.row-wrong-type', params: { column: 'first', type: 'money' } }],
        });
        await open();

        await fillIn('[data-test-cell="1.code"]', 'RX');
        await click('[data-test-save-rows]');

        assert.dom('[data-test-grid-row="2"] [data-test-cell-error]').includesText('first must be a money value');
        assert.dom('[data-test-grid-row="1"] [data-test-cell-error]').doesNotExist();
        assert.dom('[data-test-unsaved]').exists('the edits are kept');
    });

    test('a refusal that is about the whole table is listed under the grid', async function (assert) {
        this.reply = () => ({ refused: 'rows-changed', problems: [{ path: '', key: 'enhanced-pricing.admin.rows-changed', params: {} }] });
        await open();

        await fillIn('[data-test-cell="1.code"]', 'RX');
        await click('[data-test-save-rows]');

        assert.dom('[data-test-problems]').includesText('Another change was saved');
    });

    test('rows are deleted and added, and discarding restores the saved rows', async function (assert) {
        await open();

        await click('[data-test-grid-row="1"] [data-test-delete-row]');
        assert.dom('[data-test-grid-row]').exists({ count: 1 });
        assert.dom('[data-test-cell="1.code"]').hasValue('R2');

        await click('[data-test-add-row]');
        assert.dom('[data-test-grid-row]').exists({ count: 2 });

        await click('[data-test-discard-rows]');
        assert.dom('[data-test-grid-row]').exists({ count: 2 });
        assert.dom('[data-test-cell="1.code"]').hasValue('R1');
        assert.dom('[data-test-unsaved]').doesNotExist();
    });

    test('deleting every row can be saved', async function (assert) {
        await open();

        await click('[data-test-delete-row]');
        await click('[data-test-delete-row]');
        await click('[data-test-save-rows]');

        assert.deepEqual(this.calls[0].data.rows, []);
    });

    test('cells copied from Excel paste across columns and rows, adding rows', async function (assert) {
        await open();

        await triggerEvent('[data-test-cell="2.code"]', 'paste', { clipboardData: { getData: () => 'R2\t11.00\t7\r\nR9\t"1,5"\t\r\n' } });

        assert.dom('[data-test-grid-row]').exists({ count: 3 });
        assert.dom('[data-test-cell="2.first"]').hasValue('11.00');
        assert.dom('[data-test-cell="2.included"]').hasValue('7');
        assert.dom('[data-test-cell="3.code"]').hasValue('R9');
        assert.dom('[data-test-cell="3.first"]').hasValue('1,5', 'a quoted cell is one cell');
        assert.dom('[data-test-unsaved]').exists();
    });

    test('a single pasted value is left to the browser', async function (assert) {
        await open();
        let prevented = null;
        const input = document.querySelector('[data-test-cell="1.code"]');
        input.addEventListener('paste', (event) => (prevented = event.defaultPrevented));

        await triggerEvent(input, 'paste', { clipboardData: { getData: () => 'plain' } });

        assert.false(prevented);
        assert.dom('[data-test-unsaved]').doesNotExist();
    });

    test('Enter and the arrow keys move to the same column of the next row', async function (assert) {
        await open();

        await triggerKeyEvent('[data-test-cell="1.first"]', 'keydown', 'Enter');
        assert.dom('[data-test-cell="2.first"]').isFocused();

        await triggerKeyEvent('[data-test-cell="2.first"]', 'keydown', 'ArrowUp');
        assert.dom('[data-test-cell="1.first"]').isFocused();
    });

    test('a file is sent as base64 with its name, merging unless asked to replace', async function (assert) {
        await open();
        const file = new File(['code,first\nR1,1\n'], 'rows.csv', { type: 'text/csv' });

        await choose(this, file);
        await click('[data-test-replace]');
        await choose(this, file);

        assert.deepEqual(this.calls[0], {
            method: 'post',
            path: 'admin/rate-cards/cust/import',
            data: { card: 'Main', table: 'rows', filename: 'rows.csv', content: btoa('code,first\nR1,1\n'), replace: false },
        });
        assert.true(this.calls[1].data.replace);
        assert.strictEqual(this.changed, 2);
    });

    test("a file the server refuses lists its problems, and a file over 2 MB isn't sent", async function (assert) {
        this.reply = () => ({ refused: 'invalid', problems: [{ path: '3.values.first', key: 'enhanced-pricing.store.row-wrong-type', params: { column: 'first', type: 'money' } }] });
        await open();

        await choose(this, new File(['x'], 'rows.csv'));
        assert.dom('[data-test-problem="3.values.first"]').exists();
        assert.dom('[data-test-cell-error]').doesNotExist('file rows are not grid rows');

        this.calls.length = 0;
        await choose(this, new File([new Uint8Array(2 * 1024 * 1024 + 1)], 'big.csv'));
        assert.strictEqual(this.calls.length, 0);
        assert.dom('[data-test-problems]').includesText('too large');
    });

    test('downloads ask for the saved card and table in the chosen format', async function (assert) {
        this.reply = () => ({ filename: 'rows-Main.csv', mime: 'text/csv', content: btoa('a') });
        await open();

        await click('[data-test-download-csv]');

        assert.deepEqual(this.calls[0], { method: 'get', path: 'admin/rate-cards/cust/export', data: { card: this.card.uuid, table: 'rows', format: 'csv' } });
    });
});
