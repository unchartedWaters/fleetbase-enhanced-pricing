import { module, test } from 'qunit';
import { render, click, fillIn, select, typeIn, find } from '@ember/test-helpers';
import { hbs } from 'ember-cli-htmlbars';
import { setupEngineRenderingTest, fixture } from 'dummy/tests/helpers/engine';

// The quote preview: a sample order priced by the real engine for a customer, or for the
// profile's own values, saving nothing.
module('Integration | Component | pricing/quote-preview', (hooks) => {
    setupEngineRenderingTest(hooks);

    hooks.beforeEach(function () {
        const profile = fixture('profile');
        this.profile = profile;
        this.definition = profile.version.definition;
        this.customers = fixture('customers').customers;
        this.sent = [];
        this.answer = () => Promise.resolve(fixture('preview'));
        this.price = (input) => {
            this.sent.push(input);

            return this.answer(input);
        };
        this.loadCustomer = () => Promise.resolve(fixture('customer'));
    });

    async function fillOrder(context) {
        await select('[data-test-customer-select]', context.customers[0].uuid);
        await select('[data-test-service-rate]', 'tiered');
        await select('[data-test-row-select]', fixture('customer').cards[0].rows[0].uuid);
        await fillIn('[data-test-pickup-date]', '2027-03-06');
        await fillIn('[data-test-pickup-time]', '11:00');
        await click('[data-test-attribute="extra"] input');
        await fillIn('[data-test-entity="0"] [data-test-weight]', '36');
        await click('[data-test-add-entity]');
        await fillIn('[data-test-entity="1"] [data-test-weight]', '5');
        await select('[data-test-entity="1"] [data-test-entity-type]', 'separate');
    }

    test('the order is sent as the server takes it', async function (assert) {
        await render(
            hbs`<Pricing::QuotePreview @profile={{this.profile}} @definition={{this.definition}} @customers={{this.customers}} @onLoadCustomer={{this.loadCustomer}} @onPrice={{this.price}} />`
        );
        await fillOrder(this);
        await click('[data-test-price]');

        const [sent] = this.sent;
        assert.strictEqual(sent.customer, this.customers[0].uuid);
        assert.strictEqual(sent.profile, undefined, "a customer's profile is its own");
        assert.strictEqual(sent.service_rate, 'tiered');
        assert.strictEqual(sent.row, fixture('customer').cards[0].rows[0].uuid);
        assert.strictEqual(sent.pickup_at, '2027-03-06T11:00', 'as typed, read by the server in the timezone');
        assert.strictEqual(sent.timezone, 'UTC');
        assert.deepEqual(sent.attributes, { extra: true });
        assert.deepEqual(sent.entities, [
            { type: null, weight: '36', weight_unit: 'kg', length: null, width: null, height: null, dimensions_unit: 'cm', attributes: {} },
            { type: 'separate', weight: '5', weight_unit: 'kg', length: null, width: null, height: null, dimensions_unit: 'cm', attributes: {} },
        ]);
    });

    // The host console's FormatJS polyfill formats every timezone as UTC. The pickup must still
    // reach the server on the order's own clock, with its timezone.
    test("a pickup is sent on the order's clock, whatever the browser's Intl does with timezones", async function (assert) {
        const native = Intl.DateTimeFormat;
        Intl.DateTimeFormat = class extends native {
            constructor(locale, options = {}) {
                super(locale, { ...options, timeZone: 'UTC' });
            }
        };
        try {
            await render(
                hbs`<Pricing::QuotePreview @profile={{this.profile}} @definition={{this.definition}} @customers={{this.customers}} @onLoadCustomer={{this.loadCustomer}} @onPrice={{this.price}} />`
            );
            await fillOrder(this);
            await fillIn('[data-test-pickup-time]', '22:30');
            await fillIn('[data-test-timezone]', 'America/Edmonton');
            await click('[data-test-price]');
        } finally {
            Intl.DateTimeFormat = native;
        }

        const [sent] = this.sent;
        assert.strictEqual(sent.pickup_at, '2027-03-06T22:30');
        assert.strictEqual(sent.timezone, 'America/Edmonton');
    });

    test('the quote shows each line and the total in the profile currency', async function (assert) {
        await render(
            hbs`<Pricing::QuotePreview @profile={{this.profile}} @definition={{this.definition}} @customers={{this.customers}} @onLoadCustomer={{this.loadCustomer}} @onPrice={{this.price}} />`
        );
        await fillOrder(this);
        await click('[data-test-price]');

        assert.dom('[data-test-line]').exists({ count: 8 });
        assert.dom('[data-test-line="0"]').includesText('Tiered delivery');
        assert.dom('[data-test-line="0"] [data-test-amount]').hasText('$54.00');
        assert.dom('[data-test-total]').hasText('$125.54');
        assert.dom('[data-test-line="0"] [data-test-billing-code]').hasText('TIERED');
        assert.dom('[data-test-line="0"] [data-test-product]').doesNotExist();
    });

    test("a line billed under a route's own code shows its product too", async function (assert) {
        const answer = structuredClone(fixture('preview'));
        answer.quote.lines[0].billing_code = 'R-NORTH';
        this.answer = () => Promise.resolve(answer);
        await render(
            hbs`<Pricing::QuotePreview @profile={{this.profile}} @definition={{this.definition}} @customers={{this.customers}} @onLoadCustomer={{this.loadCustomer}} @onPrice={{this.price}} />`
        );
        await fillOrder(this);
        await click('[data-test-price]');

        assert.dom('[data-test-line="0"] [data-test-billing-code]').includesText('R-NORTH');
        assert.dom('[data-test-line="0"] [data-test-product]').includesText('TIERED');
    });

    test("without a customer the profile's own values are priced", async function (assert) {
        await render(
            hbs`<Pricing::QuotePreview @profile={{this.profile}} @definition={{this.definition}} @customers={{this.customers}} @onLoadCustomer={{this.loadCustomer}} @onPrice={{this.price}} />`
        );
        await select('[data-test-service-rate]', 'distance');
        await fillIn('[data-test-distance]', '12.5');
        await fillIn('[data-test-pickup-date]', '2027-03-06');
        await fillIn('[data-test-pickup-time]', '11:00');
        await click('[data-test-price]');

        assert.strictEqual(this.sent[0].profile, 'main');
        assert.strictEqual(this.sent[0].customer, undefined);
        assert.strictEqual(this.sent[0].distance_meters, 12500, 'km in meters');
        assert.dom('[data-test-row-select]').doesNotExist('no rate card without a customer');
    });

    test('a pricing failure is shown in words', async function (assert) {
        this.answer = () => Promise.resolve(fixture('failed'));
        await render(
            hbs`<Pricing::QuotePreview @profile={{this.profile}} @definition={{this.definition}} @customers={{this.customers}} @onLoadCustomer={{this.loadCustomer}} @onPrice={{this.price}} />`
        );
        await fillOrder(this);
        await click('[data-test-price]');

        assert.dom('[data-test-failed]').hasText('The order has no entities to price.');
        assert.dom('[data-test-total]').doesNotExist();
    });

    test('a stale customer and skipped dated changes are named', async function (assert) {
        this.answer = () =>
            Promise.resolve({
                quote: null,
                failed: null,
                stale: [{ path: 'fees.heavy.minimum', key: 'enhanced-pricing.profile.unknown-override', params: { path: 'fees.heavy.minimum' } }],
                skipped_changes: [{ path: 'fees.heavy.minimum', effective_from: '2026-12-01' }],
            });
        await render(
            hbs`<Pricing::QuotePreview @profile={{this.profile}} @definition={{this.definition}} @customers={{this.customers}} @onLoadCustomer={{this.loadCustomer}} @onPrice={{this.price}} />`
        );
        await fillOrder(this);
        await click('[data-test-price]');

        assert.dom('[data-test-stale]').includesText("fees.heavy.minimum isn't a value that can be overridden.");
        assert.dom('[data-test-skipped]').includesText('fees.heavy.minimum from 2026-12-01');
    });

    test('a refused request shows each problem with its field', async function (assert) {
        this.answer = () => Promise.reject({ refused: 'invalid', problems: [{ path: 'pickup_at', key: 'enhanced-pricing.admin.field-invalid', params: {} }] });
        await render(
            hbs`<Pricing::QuotePreview @profile={{this.profile}} @definition={{this.definition}} @customers={{this.customers}} @onLoadCustomer={{this.loadCustomer}} @onPrice={{this.price}} />`
        );
        await fillOrder(this);
        await click('[data-test-price]');

        assert.dom('[data-test-problems]').includesText("pickup_at: This value isn't valid.");
    });

    test('a customer opened from its page is chosen, with its rows', async function (assert) {
        this.initial = this.customers[0].uuid;
        await render(
            hbs`<Pricing::QuotePreview @profile={{this.profile}} @definition={{this.definition}} @customers={{this.customers}} @initialCustomer={{this.initial}} @onLoadCustomer={{this.loadCustomer}} @onPrice={{this.price}} />`
        );
        await select('[data-test-service-rate]', 'tiered');

        assert.dom('[data-test-customer-select]').hasValue(this.initial);
        assert.dom('[data-test-row-select] option').exists({ count: 3 }, 'none and the two rows');
    });

    const preview = hbs`<Pricing::QuotePreview @profile={{this.profile}} @definition={{this.definition}} @customers={{this.customers}} @onLoadCustomer={{this.loadCustomer}} @onPrice={{this.price}} />`;

    test('a weight is typed into the same field, one key at a time', async function (assert) {
        await render(preview);
        const field = find('[data-test-entity="0"] [data-test-weight]');

        await typeIn(field, '36');

        assert.strictEqual(find('[data-test-entity="0"] [data-test-weight]'), field, 'the field is not drawn again');
        assert.dom(field).hasValue('36');
    });

    test("removing a charge leaves another charge's amount and its error in place", async function (assert) {
        await render(preview);
        await select('[data-test-service-rate]', 'tiered');
        await select('[data-test-add-charge]', 'entered');
        await select('[data-test-add-charge]', 'entered');
        await select('[data-test-add-charge]', 'hourly');
        await fillIn('[data-test-charge="1"] input', '1.234');
        await fillIn('[data-test-charge="2"] input', '2');

        await click('[data-test-charge="0"] [data-test-remove-charge]');

        assert.dom('[data-test-charge="0"] input').hasValue('1.234');
        assert.dom('[data-test-charge="1"] input').hasValue('2');
        assert.dom('[data-test-price]').isDisabled();
    });
});
