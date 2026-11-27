// The profile editor is drawn from the server's field spec (GET admin/form, Admin/ProfileForm), so
// the console never hard-codes the profile format. These are the rules it follows. Every function
// is pure: a profile is edited as plain JSON and each change makes a new copy.

export function kindOf(form, name) {
    return form?.kinds?.[name] ?? { fields: [] };
}

export function variantNames(kind) {
    return Object.keys(kind.variants ?? {});
}

// A manual charge has no discriminator: it is the variant whose own field it sets. A stored
// charge holds both fields, one of them null.
export function variantOf(kind, item) {
    const variants = variantNames(kind);
    if (variants.length === 0) {
        return null;
    }
    if (kind.discriminator) {
        return item?.[kind.discriminator] ?? null;
    }

    return variants.find((variant) => kind.variants[variant].some((field) => isSet(item?.[field.name]) && !field.optional)) ?? variants[0];
}

function isSet(value) {
    return value !== null && value !== undefined;
}

function allFields(kind, variant) {
    return [...(kind.fields ?? []), ...(kind.variants?.[variant] ?? [])];
}

// A field the element leaves out while its condition's field is unset takes that field's default,
// as the server does, so a profile saved before the field existed still shows the right fields.
function applies(field, item, siblings = []) {
    if (!field.when) {
        return true;
    }

    return Object.entries(field.when).every(([name, values]) => {
        const sibling = siblings.find((other) => other.name === name);
        const actual = item?.[name] ?? (sibling?.optional ? sibling.default : undefined);

        return values.includes(actual);
    });
}

export function fieldsFor(kind, item) {
    const all = allFields(kind, variantOf(kind, item));

    return all.filter((field) => applies(field, item, all));
}

// After a field changes, the fields whose condition no longer holds go back to their default
// (null for an amount beside a percentage), so a value that is hidden is not sent.
export function settle(kind, item) {
    const all = allFields(kind, variantOf(kind, item));
    const next = { ...item };
    for (const field of all) {
        if (!applies(field, next, all) && field.name in next) {
            if (field.optional && 'default' in field) {
                next[field.name] = structuredCopy(field.default);
            } else {
                delete next[field.name];
            }
        }
    }
    // A percentage is charged once per order, which no other charge choice allows.
    if (next.mode === 'percentage' && 'charge' in next) {
        next.charge = 'per_order';
    }

    return next;
}

// The value a field starts with: its default when it may be left out, else a blank the person
// fills in (and the server refuses until they do).
export function blankFor(form, field) {
    if (field.optional && 'default' in field) {
        return structuredCopy(field.default);
    }
    if (field.nullable) {
        return null;
    }
    switch (field.type) {
        case 'list':
        case 'refs':
        case 'dates':
            return [];
        case 'key':
        case 'code':
        case 'text':
            return '';
        case 'boolean':
            return false;
        case 'object':
            return newObject(form, field.of);
        case 'choice': {
            const [first] = Object.keys(field.choices ?? {});

            return first ? { [first]: field.choices[first].type === 'text' ? '' : null } : {};
        }
        default:
            return null;
    }
}

function structuredCopy(value) {
    return value === undefined ? undefined : JSON.parse(JSON.stringify(value));
}

function newObject(form, kindName, variant = null) {
    const kind = kindOf(form, kindName);
    const chosen = variant ?? variantNames(kind)[0] ?? null;
    const item = {};
    if (kind.discriminator && chosen) {
        item[kind.discriminator] = chosen;
    }
    const all = allFields(kind, chosen);
    for (const field of all) {
        if (applies(field, item, all)) {
            item[field.name] = blankFor(form, field);
        }
    }

    return item;
}

export function newItem(form, kindName, variant = null) {
    return newObject(form, kindName, variant);
}

// A variant switch keeps every field the two variants share, starts the new variant's own
// fields, and drops the old variant's. Fields the format doesn't know are kept for the person
// to remove.
export function switchVariant(form, kind, item, variant) {
    const before = new Set(fieldsFor(kind, item).map((field) => field.name));
    const known = knownNames(kind);
    const next = {};
    for (const [name, value] of Object.entries(item ?? {})) {
        if (!known.has(name) && name !== kind.discriminator) {
            next[name] = value;
        }
    }
    if (kind.discriminator) {
        next[kind.discriminator] = variant;
    }
    for (const field of allFields(kind, variant)) {
        next[field.name] = before.has(field.name) && field.name in (item ?? {}) ? item[field.name] : blankFor(form, field);
    }
    const all = allFields(kind, variant);
    for (const field of all) {
        if (!applies(field, next, all)) {
            delete next[field.name];
        }
    }

    return next;
}

function knownNames(kind) {
    return new Set([...(kind.fields ?? []), ...Object.values(kind.variants ?? {}).flat()].map((field) => field.name));
}

// Fields the element holds that its variant doesn't use. The server stores every optional field,
// so a field of another variant, or one whose condition isn't met, is held as null: that is not
// unknown.
export function unknownFields(kind, item) {
    const used = new Set(fieldsFor(kind, item).map((field) => field.name));
    if (kind.discriminator) {
        used.add(kind.discriminator);
    }
    const known = knownNames(kind);

    return Object.keys(item ?? {}).filter((name) => !used.has(name) && !(known.has(name) && item[name] === null));
}

function list(value) {
    return Array.isArray(value) ? value : [];
}

function keyed(items) {
    return list(items)
        .filter((item) => item && typeof item.key === 'string')
        .map((item) => ({ value: item.key, label: item.name ? `${item.key} · ${item.name}` : item.key }));
}

const COLUMN_TYPES = { text_columns: 'text', money_columns: 'money', integer_columns: 'integer' };

// What a reference may name. Columns come from the element's own rate table: a rate table's
// row key from its own columns, a service rate's from the table it names. Anything else (an entity
// type's own price) may use a column of any table. An additional fee's choice comes from its
// own attribute.
export function refOptions(ref, definition, context) {
    switch (ref) {
        case 'products':
            return list(definition?.products)
                .filter((product) => product && typeof product.code === 'string')
                .map((product) => ({ value: product.code, label: product.name ? `${product.code} · ${product.name}` : product.code }));
        case 'entity_types':
        case 'rate_tables':
        case 'attributes':
        case 'service_rates':
            return keyed(definition?.[ref]);
        case 'holiday_lists':
            return mapToEntries(definition?.holiday_lists).map((entry) => ({ value: entry.name, label: entry.name }));
        case 'columns':
            return keyed(context?.columns);
        case 'choices': {
            const attribute = list(definition?.attributes).find((item) => item?.key === context?.attribute);

            return attribute?.type === 'choice' ? keyed(attribute.choices) : [];
        }
        default: {
            const type = COLUMN_TYPES[ref];
            if (!type) {
                return [];
            }
            let tables = list(definition?.rate_tables);
            if (context && 'rate_table' in context) {
                tables = tables.filter((table) => table?.key === context.rate_table);
            }
            const seen = new Set();

            return tables
                .flatMap((table) => list(table?.columns))
                .filter((column) => column?.type === type && !seen.has(column.key) && seen.add(column.key))
                .map((column) => ({ value: column.key, label: column.key }));
        }
    }
}

export function getIn(value, path) {
    return path.reduce((current, segment) => (current === null || current === undefined ? undefined : current[segment]), value);
}

export function setIn(value, path, next) {
    if (path.length === 0) {
        return next;
    }
    const [segment, ...rest] = path;
    const copy = Array.isArray(value) ? [...value] : { ...value };
    copy[segment] = setIn(copy[segment], rest, next);

    return copy;
}

export function withoutKey(value, name) {
    const copy = { ...value };
    delete copy[name];

    return copy;
}

export function moveItem(items, from, to) {
    if (to < 0 || to >= items.length || from < 0 || from >= items.length) {
        return [...items];
    }
    const copy = [...items];
    const [moved] = copy.splice(from, 1);
    copy.splice(to, 0, moved);

    return copy;
}

export function removeItem(items, index) {
    return items.filter((_, position) => position !== index);
}

function within(path, prefix) {
    return path === prefix || path.startsWith(`${prefix}.`);
}

export function problemsAt(problems, path) {
    return list(problems).filter((problem) => problem.path === path);
}

export function problemsWithin(problems, prefix) {
    return list(problems).filter((problem) => within(problem.path ?? '', prefix));
}

// Problem paths are positions (decision 28), so a move or a removal makes them point at the wrong
// element: the editor clears the marks of that section instead.
export function withoutProblemsWithin(problems, prefix) {
    return list(problems).filter((problem) => !within(problem.path ?? '', prefix));
}

// Holiday lists are a map of name to dates. An empty map comes back from the server as [].
export function mapToEntries(map) {
    if (!map || typeof map !== 'object' || Array.isArray(map)) {
        return [];
    }

    return Object.entries(map).map(([name, dates]) => ({ name, dates }));
}

export function entriesToMap(entries) {
    const map = {};
    for (const entry of entries) {
        map[entry.name] = entry.dates;
    }

    return map;
}

export function pathText(path) {
    return path.join('.');
}

// A name as a key: lowercase letters and digits joined by "-", at most 64 characters (the key
// format of server/src/Profile/Schema.php). The person can change what is offered.
export function slug(name) {
    return String(name ?? '')
        .normalize('NFKD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .slice(0, 64)
        .replace(/^-+|-+$/g, '');
}

// The help text for a field of a kind: `help.<kind>.<field>`, else `help.field.<field>`.
export function helpFor(intl, kind, name) {
    for (const key of [`enhanced-pricing.console.help.${kind}.${name}`, `enhanced-pricing.console.help.field.${name}`]) {
        if (intl.exists(key)) {
            return intl.t(key);
        }
    }

    return null;
}
