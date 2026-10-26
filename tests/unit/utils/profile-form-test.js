import { module, test } from 'qunit';
import fixtures from 'dummy/tests/helpers/console-fixtures';
import {
    kindOf,
    variantOf,
    fieldsFor,
    switchVariant,
    newItem,
    unknownFields,
    refOptions,
    setIn,
    getIn,
    moveItem,
    removeItem,
    problemsAt,
    problemsWithin,
    withoutProblemsWithin,
    mapToEntries,
    entriesToMap,
} from '@unchartedwaters/enhanced-pricing-engine/utils/profile-form';

// The profile editor draws every element of a profile from the server's field spec. These are
// the rules it follows: which fields an element has, how a variant switch keeps shared fields,
// how references list what the profile holds, and how problems are found by path.
const form = fixtures.form;
const definition = fixtures.profile.version.definition;
const names = (fields) => fields.map((field) => field.name);

module('Unit | Utility | profile-form', () => {
    test("an element has its common fields and its variant's fields", (assert) => {
        const serviceRate = kindOf(form, 'service_rate');
        const tiered = definition.service_rates[0];

        assert.strictEqual(variantOf(serviceRate, tiered), 'tiers');
        assert.deepEqual(names(fieldsFor(serviceRate, tiered)), ['key', 'name', 'product', 'rate_table', 'line_name', 'minimum', 'enabled', 'tiers']);
        assert.deepEqual(names(fieldsFor(serviceRate, definition.service_rates[1])).slice(-1), ['per_meter']);
    });

    test("a manual charge's variant is the amount field it sets", (assert) => {
        const charge = kindOf(form, 'manual_charge');

        assert.strictEqual(variantOf(charge, { key: 'a', amount: 100 }), 'amount');
        assert.strictEqual(variantOf(charge, { key: 'a', entered: { minimum: 1, maximum: 2 } }), 'entered');
        assert.strictEqual(variantOf(charge, { key: 'a' }), 'amount');
        assert.strictEqual(variantOf(charge, definition.manual_charges[0]), 'amount', 'entered is null');
        assert.strictEqual(variantOf(charge, definition.manual_charges[1]), 'entered', 'amount is null');
        assert.deepEqual(unknownFields(charge, definition.manual_charges[1]), [], "the other variant's null fields are not unknown");
    });

    test('a field used only for some sibling values is left out otherwise', (assert) => {
        const fee = kindOf(form, 'fee');
        const byWeight = { kind: 'parcel', basis: 'weight', measure: 'dimensional' };

        assert.true(names(fieldsFor(fee, byWeight)).includes('measure'));
        assert.true(names(fieldsFor(fee, byWeight)).includes('divisor'));
        assert.false(names(fieldsFor(fee, { ...byWeight, measure: 'actual' })).includes('divisor'));
        assert.false(names(fieldsFor(fee, { kind: 'parcel', basis: 'volume' })).includes('measure'));
    });

    test('switching a variant keeps the fields both share and starts the new ones', (assert) => {
        const fee = kindOf(form, 'fee');
        const peak = definition.fees.find((item) => item.kind === 'peak_hours');

        const parcel = switchVariant(form, fee, peak, 'parcel');

        assert.strictEqual(parcel.kind, 'parcel');
        assert.strictEqual(parcel.key, peak.key);
        assert.deepEqual(parcel.applies_to, peak.applies_to);
        assert.strictEqual(parcel.charge, peak.charge, 'charge is in both variants');
        assert.false('window' in parcel, 'peak hours only');
        assert.deepEqual(parcel.thresholds, [], 'a required list starts empty');
        assert.strictEqual(parcel.basis, null, 'a required value starts blank');
    });

    test('switching a manual charge to an entered amount drops the fixed amount', (assert) => {
        const charge = kindOf(form, 'manual_charge');
        const hourly = definition.manual_charges[0];

        const entered = switchVariant(form, charge, hourly, 'entered');

        assert.false('amount' in entered);
        assert.false('quantity' in entered);
        assert.deepEqual(entered.entered, { minimum: null, maximum: null });
    });

    test('a new element has defaults for optional fields and blanks for required ones', (assert) => {
        const item = newItem(form, 'subtotal_fee', 'flat');

        assert.strictEqual(item.mode, 'flat');
        assert.strictEqual(item.key, '');
        assert.deepEqual(item.applies_to, []);
        assert.deepEqual(item.requires, []);
        assert.strictEqual(item.enabled, true);
        assert.strictEqual(item.amount, null);
    });

    test("fields the format doesn't know are listed so they can be removed", (assert) => {
        const product = kindOf(form, 'product');
        const fee = kindOf(form, 'fee');

        assert.deepEqual(unknownFields(product, { code: 'A', name: 'A', priced: true }), ['priced']);
        assert.deepEqual(unknownFields(fee, { kind: 'parcel', basis: 'volume', measure: 'actual' }), ['measure'], 'a value set where it is not used');
        assert.deepEqual(unknownFields(product, { code: 'A', name: 'A' }), []);
        assert.deepEqual(unknownFields(fee, { kind: 'parcel', basis: 'volume', measure: 'dimensional' }), ['measure'], 'a value is kept');
    });

    test("a stored element's empty fields of another variant or condition aren't unknown", (assert) => {
        for (const section of ['fees', 'subtotal_fees', 'manual_charges', 'service_rates']) {
            const kind = kindOf(form, form.sections.find((item) => item.key === section).kind);
            for (const item of definition[section]) {
                assert.deepEqual(unknownFields(kind, item), [], `${section} ${item.key}`);
            }
        }
    });

    test("references list what the profile holds, in the element's own context", (assert) => {
        const values = (options) => options.map((option) => option.value);
        const tiered = definition.service_rates[0];
        const rows = definition.rate_tables[0];

        assert.deepEqual(values(refOptions('products', definition, {})).slice(0, 2), ['TIERED', 'SEPARATE']);
        assert.strictEqual(refOptions('products', definition, {})[0].label, 'TIERED · Delivery');
        assert.deepEqual(values(refOptions('rate_tables', definition, {})), ['rows']);
        assert.deepEqual(
            values(refOptions('columns', definition, rows)),
            rows.columns.map((column) => column.key)
        );
        assert.deepEqual(values(refOptions('money_columns', definition, tiered)), ['first', 'second', 'third', 'separate_price']);
        assert.deepEqual(values(refOptions('text_columns', definition, tiered)), ['code']);
        assert.deepEqual(values(refOptions('money_columns', definition, definition.service_rates[1])), [], 'no rate table, no columns');
        assert.deepEqual(values(refOptions('service_rates', definition, {})), ['tiered', 'distance']);
    });

    test("an additional fee chooses from its own attribute's choices", (assert) => {
        const profile = {
            attributes: [
                { key: 'size', type: 'choice', choices: [{ key: 'large', name: 'Large' }] },
                { key: 'extra', type: 'boolean' },
            ],
        };

        assert.deepEqual(refOptions('choices', profile, { attribute: 'size' }), [{ value: 'large', label: 'large · Large' }]);
        assert.deepEqual(refOptions('choices', profile, { attribute: 'extra' }), []);
    });

    test('values are read and set by path without changing the original', (assert) => {
        const original = { fees: [{ key: 'a', window: null }] };

        const changed = setIn(original, ['fees', 0, 'window'], { from: '08:00', to: '10:00' });

        assert.strictEqual(original.fees[0].window, null);
        assert.strictEqual(getIn(changed, ['fees', 0, 'window', 'from']), '08:00');
        assert.strictEqual(getIn(changed, ['fees', 5, 'key']), undefined);
    });

    test('rules move and are removed in place', (assert) => {
        assert.deepEqual(moveItem(['a', 'b', 'c'], 2, 0), ['c', 'a', 'b']);
        assert.deepEqual(moveItem(['a', 'b', 'c'], 0, 1), ['b', 'a', 'c']);
        assert.deepEqual(moveItem(['a', 'b'], 0, -1), ['a', 'b'], 'out of range stays put');
        assert.deepEqual(removeItem(['a', 'b', 'c'], 1), ['a', 'c']);
    });

    test('problems are found at a field and within an element, and cleared within one', (assert) => {
        const problems = fixtures.refused.invalid.problems;

        assert.deepEqual(
            problemsAt(problems, 'fees.0.minimum').map((problem) => problem.key),
            ['enhanced-pricing.profile.out-of-range']
        );
        assert.strictEqual(problemsWithin(problems, 'fees').length, 1);
        assert.strictEqual(problemsWithin(problems, 'fees.0').length, 1);
        assert.strictEqual(problemsWithin(problems, 'fees.1').length, 0);
        assert.strictEqual(problemsWithin(problems, 'fee').length, 0, 'a path prefix is a whole segment');
        assert.deepEqual(
            withoutProblemsWithin(problems, 'fees').map((problem) => problem.path),
            ['products.1.code']
        );
    });

    test('holiday lists are edited as named entries and saved as a map', (assert) => {
        assert.deepEqual(mapToEntries({ canada: ['2026-12-25'] }), [{ name: 'canada', dates: ['2026-12-25'] }]);
        assert.deepEqual(mapToEntries([]), [], 'an empty map can come back as an empty list');
        assert.deepEqual(entriesToMap([{ name: 'canada', dates: ['2026-12-25'] }]), { canada: ['2026-12-25'] });
    });
});
