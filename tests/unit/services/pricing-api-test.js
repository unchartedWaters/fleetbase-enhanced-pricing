import { module, test } from 'qunit';
import { setupTest } from 'dummy/tests/helpers';
import PricingApi from '@unchartedwaters/enhanced-pricing-engine/services/pricing-api';
import Service from '@ember/service';

// Every console call goes to the extension's own endpoints and keeps the server's refusal body,
// which names why it refused (fetch's rawError drops the status).
module('Unit | Service | pricing-api', (hooks) => {
    setupTest(hooks);

    function api(owner, answer) {
        const calls = [];
        class FetchStub extends Service {
            request(method, path, data, options) {
                calls.push({ method, path, data, options });

                return answer();
            }
            get(path, query, options) {
                return this.request('get', path, query, options);
            }
            post(path, data, options) {
                return this.request('post', path, data, options);
            }
            put(path, data, options) {
                return this.request('put', path, data, options);
            }
            delete(path, data, options) {
                return this.request('delete', path, data, options);
            }
        }
        owner.register('service:fetch', FetchStub);
        owner.register('service:pricing-api', PricingApi);

        return { service: owner.lookup('service:pricing-api'), calls };
    }

    test("calls go to the extension's namespace and keep refusal bodies", async function (assert) {
        const { service, calls } = api(this.owner, () => Promise.resolve({ ok: true }));

        await service.get('admin/profiles/main', { version: 2 });
        await service.put('admin/profiles/main', { built_on: 2 });
        await service.delete('admin/dated-changes/x');

        assert.deepEqual(
            calls.map((call) => [call.method, call.path, call.data]),
            [
                ['get', 'admin/profiles/main', { version: 2 }],
                ['put', 'admin/profiles/main', { built_on: 2 }],
                ['delete', 'admin/dated-changes/x', {}],
            ]
        );
        for (const call of calls) {
            assert.deepEqual(call.options, { namespace: 'enhanced-pricing/int/v1', rawError: true });
        }
    });

    test('a key is escaped in the path', async function (assert) {
        const { service, calls } = api(this.owner, () => Promise.resolve({}));

        await service.get(`admin/profiles/${service.segment('a/b c')}`);

        assert.strictEqual(calls[0].path, 'admin/profiles/a%2Fb%20c');
    });

    test('a refusal comes back as the server sent it', async function (assert) {
        const body = { refused: 'stale', stale: [] };
        const { service } = api(this.owner, () => Promise.reject(body));

        await assert.rejects(service.post('admin/profiles', {}), (error) => error === body);
    });

    test('any other failure becomes an error refusal with its message', async function (assert) {
        const { service } = api(this.owner, () => Promise.reject(new Error('Network down')));

        await assert.rejects(service.get('admin/profiles'), (error) => error.refused === 'error' && error.message === 'Network down');
    });

    test('a Fleetbase error body keeps its first message', async function (assert) {
        const { service } = api(this.owner, () => Promise.reject({ errors: ['Unauthenticated.'] }));

        await assert.rejects(service.get('admin/profiles'), (error) => error.refused === 'error' && error.message === 'Unauthenticated.');
    });
});
