import { module, test } from 'qunit';
import { setupTest } from 'dummy/tests/helpers';
import Service from '@ember/service';
import PreviewRoute from '@unchartedwaters/enhanced-pricing-engine/routes/preview';
import ProfilesIndexRoute from '@unchartedwaters/enhanced-pricing-engine/routes/profiles/index';
import NewProfileRoute from '@unchartedwaters/enhanced-pricing-engine/routes/new-profile';
import { fixture } from 'dummy/tests/helpers/engine';

// The routes load what each screen shows through the pricing API.
module('Unit | Route | console routes', (hooks) => {
    setupTest(hooks);

    function api(owner, answers) {
        const asked = [];
        class ApiStub extends Service {
            segment(value) {
                return encodeURIComponent(value);
            }
            get(path, query) {
                asked.push([path, query]);
                const answer = answers[path];

                return answer === undefined ? Promise.reject({ refused: 'not-found' }) : Promise.resolve(JSON.parse(JSON.stringify(answer)));
            }
            form() {
                return Promise.resolve(fixture('form'));
            }
        }
        owner.register('service:pricing-api', ApiStub);

        return asked;
    }

    test('the preview shows the first profile and its first customers by default', async function (assert) {
        api(this.owner, {
            'admin/profiles': fixture('profiles'),
            'admin/profiles/main': fixture('profile'),
            'admin/rate-cards': fixture('customers'),
        });
        this.owner.register('route:test-preview', PreviewRoute);

        const model = await this.owner.lookup('route:test-preview').model({ profile: null, customer: null });

        assert.strictEqual(model.key, 'main');
        assert.strictEqual(model.customers.length, 2);
        assert.strictEqual(model.customer, null);
        assert.false(model.more);
    });

    test('a customer asked for beyond the first page is added to the choices', async function (assert) {
        const customer = { ...fixture('customer'), uuid: 'later', name: 'Later customer' };
        api(this.owner, {
            'admin/profiles': fixture('profiles'),
            'admin/profiles/main': fixture('profile'),
            'admin/rate-cards': { ...fixture('customers'), total: 80 },
            'admin/rate-cards/later': customer,
        });
        this.owner.register('route:test-preview', PreviewRoute);

        const model = await this.owner.lookup('route:test-preview').model({ profile: 'main', customer: 'later' });

        assert.deepEqual(model.customers[0], { uuid: 'later', name: 'Later customer' });
        assert.strictEqual(model.customer, 'later');
        assert.true(model.more, 'the list says more customers exist');
    });

    test('with no profiles the preview has nothing to price', async function (assert) {
        api(this.owner, { 'admin/profiles': { profiles: [] } });
        this.owner.register('route:test-preview', PreviewRoute);

        const model = await this.owner.lookup('route:test-preview').model({ profile: null, customer: null });

        assert.strictEqual(model.key, null);
    });

    test("the profiles page lists the company's profiles", async function (assert) {
        api(this.owner, { 'admin/profiles': fixture('profiles') });
        this.owner.register('route:test-profiles', ProfilesIndexRoute);

        const model = await this.owner.lookup('route:test-profiles').model();

        assert.deepEqual(
            model.map((profile) => profile.key),
            ['main']
        );
    });

    test('a new profile starts with every section empty and its general fields blank', async function (assert) {
        api(this.owner, {});
        this.owner.register('route:test-new-profile', NewProfileRoute);

        const { definition } = await this.owner.lookup('route:test-new-profile').model();

        assert.deepEqual(definition.products, []);
        assert.deepEqual(definition.fees, []);
        assert.deepEqual(definition.holiday_lists, {});
        assert.strictEqual(definition.currency, null);
        assert.strictEqual(definition.default_entity_type, null);
    });
});
