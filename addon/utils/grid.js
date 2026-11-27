import { decimalToMinor } from './money';

// The rate table grid's pure parts: Excel's clipboard text, a typed row as the values the server
// checks, and base64 for carrying a file in JSON. The server decides what is valid; these only
// turn what was typed into what the server reads.

// Rows of cells from text copied out of Excel or Sheets: tab-separated, line-separated, with a
// cell in quotes when it holds a tab, a line break or a quote ("" is a quote). One trailing line
// break doesn't make an empty row.
export function parseTsv(text) {
    const rows = [];
    let row = [];
    let cell = '';
    let quoted = false;
    let started = false;
    const source = String(text ?? '');
    for (let i = 0; i < source.length; i++) {
        const char = source[i];
        started = true;
        if (quoted) {
            if (char === '"' && source[i + 1] === '"') {
                cell += '"';
                i++;
            } else if (char === '"') {
                quoted = false;
            } else {
                cell += char;
            }
        } else if (char === '"' && cell === '') {
            quoted = true;
        } else if (char === '\t') {
            row.push(cell);
            cell = '';
        } else if (char === '\n' || char === '\r') {
            if (char === '\r' && source[i + 1] === '\n') {
                i++;
            }
            row.push(cell);
            rows.push(row);
            row = [];
            cell = '';
            started = false;
        } else {
            cell += char;
        }
    }
    if (started) {
        row.push(cell);
        rows.push(row);
    }

    return rows;
}

// One row's typed cells (column key => text) as values for the server, and an error for each cell
// that isn't a value of its column's type. A blank cell is left out: a missing amount.
export function cellsToValues(columns, cells, currency) {
    const values = {};
    const errors = {};
    for (const column of columns) {
        const text = String(cells[column.key] ?? '').trim();
        if (text === '') {
            continue;
        }
        if (column.type === 'money') {
            const parsed = decimalToMinor(text, currency);
            if (parsed.error) {
                errors[column.key] = parsed.error;
            } else {
                values[column.key] = parsed.value;
            }
        } else if (column.type === 'integer') {
            if (/^\d{1,9}$/.test(text)) {
                values[column.key] = Number(text);
            } else {
                errors[column.key] = 'enhanced-pricing.console.grid.integer-invalid';
            }
        } else if (column.type === 'number') {
            if (/^\d{1,7}(\.\d{1,6})?$/.test(text)) {
                values[column.key] = text;
            } else {
                errors[column.key] = 'enhanced-pricing.console.grid.number-invalid';
            }
        } else {
            values[column.key] = text;
        }
    }

    return { values, errors };
}

export function bytesToBase64(bytes) {
    let binary = '';
    for (let i = 0; i < bytes.length; i += 0x8000) {
        binary += String.fromCharCode(...bytes.subarray(i, i + 0x8000));
    }

    return btoa(binary);
}

export function base64ToBytes(text) {
    return Uint8Array.from(atob(text), (char) => char.charCodeAt(0));
}

// Hands a file the server built ({ filename, mime, content } with content in base64) to the person.
export function saveFile({ filename, mime, content }) {
    const url = URL.createObjectURL(new Blob([base64ToBytes(content)], { type: mime }));
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
}
