import Component from '@glimmer/component';
import { tracked } from '@glimmer/tracking';
import { action } from '@ember/object';
import { inject as service } from '@ember/service';
import { minorToDecimal, minorUnits } from '../../utils/money';
import { parseTsv, cellsToValues, bytesToBase64, saveFile } from '../../utils/grid';

const MAX_ROWS = 2000;
const MAX_BYTES = 2 * 1024 * 1024;

// One customer's rows of one rate table on one card, as a grid like a spreadsheet: type in a cell,
// Enter and the arrow keys move between rows, a block copied from Excel pastes across cells and
// rows, rows are added and deleted. Save replaces the table's rows with the grid's; the server
// checks every row with pricing's own rules and refuses the save with a message at the cell. A file
// can be uploaded (added to the rows, or replacing them) and the saved rows downloaded.
// The server decides what is valid; the grid only reads what was typed (utils/grid.js).
export default class PricingRateGridComponent extends Component {
    @service pricingApi;

    @tracked open = false;
    @tracked edited = null;
    @tracked dirty = false;
    @tracked problems = [];
    @tracked busy = false;
    @tracked replace = false;
    @tracked notice = null;
    @tracked savedToken = null;
    nextId = 1;

    get table() {
        return this.args.table;
    }

    get columns() {
        return this.table.columns;
    }

    get places() {
        return minorUnits(this.args.currency);
    }

    get loaded() {
        return (this.args.card?.rows ?? [])
            .filter((row) => row.table_key === this.table.key)
            .map((row) => ({
                id: `saved-${row.uuid}`,
                cells: Object.fromEntries(this.columns.map((column) => [column.key, this.show(column, row.values?.[column.key])])),
                errors: {},
            }));
    }

    get rows() {
        return this.edited ?? this.loaded;
    }

    get count() {
        return this.loaded.length;
    }

    get token() {
        return this.savedToken ?? this.args.card?.tokens?.[this.table.key] ?? null;
    }

    get numbered() {
        return this.rows.map((row, index) => ({ ...row, index, number: index + 1 }));
    }

    show(column, value) {
        if (value === null || value === undefined) {
            return '';
        }

        return column.type === 'money' && Number.isInteger(value) ? minorToDecimal(value, this.args.currency) : String(value);
    }

    label(column) {
        return column.name ?? column.key;
    }

    update(rows) {
        this.edited = rows;
        this.dirty = true;
        this.notice = null;
    }

    blankRow() {
        return { id: `new-${this.nextId++}`, cells: Object.fromEntries(this.columns.map((column) => [column.key, ''])), errors: {} };
    }

    @action toggle() {
        this.open = !this.open;
    }

    @action edit(event) {
        const { r, c } = event.target.dataset;
        const key = this.columns[Number(c)].key;
        this.update(this.rows.map((row, index) => (index === Number(r) ? { ...row, cells: { ...row.cells, [key]: event.target.value }, errors: { ...row.errors, [key]: undefined } } : row)));
    }

    @action addRow() {
        if (this.rows.length < MAX_ROWS) {
            this.update([...this.rows, this.blankRow()]);
        }
    }

    @action removeRow(index) {
        this.update(this.rows.filter((_, i) => i !== index));
    }

    @action discard() {
        this.edited = null;
        this.dirty = false;
        this.problems = [];
        this.notice = null;
    }

    @action key(event) {
        const { r, c } = event.target.dataset;
        const step = { Enter: 1, ArrowDown: 1, ArrowUp: -1 }[event.key];
        if (step === undefined || event.shiftKey || event.altKey) {
            return;
        }
        const next = event.target.closest('table').querySelector(`[data-r="${Number(r) + step}"][data-c="${c}"]`);
        if (next) {
            event.preventDefault();
            next.focus();
            next.select();
        }
    }

    // A block of cells (anything with a tab or a line break) fills the grid from the cell it is
    // pasted into, adding rows at the end; one value pastes as text as usual.
    @action paste(event) {
        const text = event.clipboardData?.getData('text/plain') ?? '';
        if (!/[\t\r\n]/.test(text)) {
            return;
        }
        event.preventDefault();
        const { r, c } = event.target.dataset;
        const block = parseTsv(text);
        const rows = [...this.rows];
        block.forEach((cells, i) => {
            const at = Number(r) + i;
            if (at >= MAX_ROWS) {
                return;
            }
            while (rows.length <= at) {
                rows.push(this.blankRow());
            }
            const next = { ...rows[at], cells: { ...rows[at].cells }, errors: { ...rows[at].errors } };
            cells.forEach((cell, j) => {
                const column = this.columns[Number(c) + j];
                if (column) {
                    next.cells[column.key] = cell;
                    next.errors[column.key] = undefined;
                }
            });
            rows[at] = next;
        });
        this.update(rows);
    }

    @action async save() {
        const rows = [];
        const marked = this.rows.map((row, index) => {
            const { values, errors } = cellsToValues(this.columns, row.cells, this.args.currency);
            if (Object.keys(values).length > 0) {
                rows.push({ line: index + 1, values });
            }

            return { ...row, errors: Object.fromEntries(Object.entries(errors).map(([key, message]) => [key, { key: message, params: { places: this.places } }])) };
        });
        this.edited = marked;
        if (marked.some((row) => Object.keys(row.errors).length > 0)) {
            this.problems = [];

            return;
        }
        await this.send(() => this.pricingApi.put(this.url('rows'), { card: this.args.card.label, table: this.table.key, rows, replace: true, token: this.token }), true);
    }

    @action async upload(event) {
        const file = event.target.files?.[0];
        event.target.value = '';
        if (!file) {
            return;
        }
        if (file.size > MAX_BYTES) {
            this.problems = [{ path: '', key: 'enhanced-pricing.admin.import-too-large', params: { max: '2 MB' } }];

            return;
        }
        const content = bytesToBase64(new Uint8Array(await file.arrayBuffer()));
        await this.send(() => this.pricingApi.post(this.url('import'), { card: this.args.card.label, table: this.table.key, filename: file.name, content, replace: this.replace }), false);
    }

    @action async download(format) {
        this.busy = true;
        try {
            saveFile(await this.pricingApi.get(this.url('export'), { card: this.args.card.uuid, table: this.table.key, format }));
        } catch (refusal) {
            this.problems = refusal.problems ?? [];
        } finally {
            this.busy = false;
        }
    }

    @action toggleReplace(event) {
        this.replace = event.target.checked;
    }

    url(action) {
        return `admin/rate-cards/${this.pricingApi.segment(this.args.customer)}/${action}`;
    }

    // Sends a save or an import. A save keeps the grid as typed; an import shows the file's rows,
    // from the reloaded customer.
    async send(call, keepRows) {
        this.busy = true;
        this.problems = [];
        try {
            const result = await call();
            this.savedToken = result.token;
            this.notice = result.rows;
            this.dirty = false;
            this.edited = keepRows ? this.edited?.map((row) => ({ ...row, errors: {} })) : null;
            await this.args.onChanged?.();
        } catch (refusal) {
            this.apply(refusal, keepRows);
        } finally {
            this.busy = false;
        }
    }

    // A problem at a row of what was sent ("5.values.first") is shown at that cell of the grid;
    // the rest, and every problem of a file, are listed.
    apply(refusal, inGrid) {
        const general = [];
        const cells = {};
        for (const problem of refusal.problems ?? []) {
            const match = /^(\d+)\.values\.(.+)$/.exec(problem.path ?? '');
            if (match && inGrid && this.edited) {
                (cells[Number(match[1]) - 1] ??= {})[match[2]] = problem;
            } else {
                general.push(problem);
            }
        }
        if (inGrid && this.edited) {
            this.edited = this.edited.map((row, index) => ({ ...row, errors: { ...cells[index] } }));
        }
        this.problems = refusal.problems ? general : [{ path: '', key: 'enhanced-pricing.console.grid.failed', params: {} }];
    }
}
